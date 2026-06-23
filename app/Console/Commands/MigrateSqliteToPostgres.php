<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\OrdenProduccion;

class MigrateSqliteToPostgres extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-sqlite-to-postgres';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from SQLite to Supabase PostgreSQL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sqlitePath = database_path('database.sqlite');

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite database file not found at: {$sqlitePath}");
            return Command::FAILURE;
        }

        $this->info("Found SQLite database at: {$sqlitePath}");

        // Configure temporary SQLite connection dynamically
        config(['database.connections.sqlite_temp' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
        ]]);

        $sqliteConn = DB::connection('sqlite_temp');

        // Check tables in SQLite
        try {
            $sqliteUsersCount = $sqliteConn->table('users')->count();
        } catch (\Exception $e) {
            $sqliteUsersCount = 0;
            $this->warn("Table 'users' does not exist or has errors in SQLite: " . $e->getMessage());
        }

        try {
            $sqliteOrdersCount = $sqliteConn->table('orden_produccions')->count();
        } catch (\Exception $e) {
            $sqliteOrdersCount = 0;
            $this->warn("Table 'orden_produccions' does not exist or has errors in SQLite: " . $e->getMessage());
        }

        $this->info("SQLite counts: Users: {$sqliteUsersCount}, Orders: {$sqliteOrdersCount}");

        // Migrate Users
        $migratedUsers = 0;
        $skippedUsers = 0;
        if ($sqliteUsersCount > 0) {
            $this->info("Migrating users...");
            $sqliteConn->table('users')->orderBy('id')->chunk(100, function ($users) use (&$migratedUsers, &$skippedUsers) {
                foreach ($users as $user) {
                    // Check if already exists in Postgres
                    if (User::where('email', $user->email)->exists()) {
                        $skippedUsers++;
                        continue;
                    }

                    // Create user preserving ID
                    $newUser = new User();
                    foreach ((array)$user as $key => $value) {
                        $newUser->{$key} = $value;
                    }
                    $newUser->save();
                    $migratedUsers++;
                }
            });
        }

        // Migrate Production Orders
        $migratedOrders = 0;
        $skippedOrders = 0;
        if ($sqliteOrdersCount > 0) {
            $this->info("Migrating production orders...");
            $sqliteConn->table('orden_produccions')->orderBy('id')->chunk(100, function ($orders) use (&$migratedOrders, &$skippedOrders) {
                foreach ($orders as $order) {
                    // Check if already exists in Postgres
                    if (OrdenProduccion::where('numero_op', $order->numero_op)->exists()) {
                        $skippedOrders++;
                        continue;
                    }

                    // Create order preserving ID
                    $newOrder = new OrdenProduccion();
                    foreach ((array)$order as $key => $value) {
                        $newOrder->{$key} = $value;
                    }
                    $newOrder->save();
                    $migratedOrders++;
                }
            });
        }

        // Reset PostgreSQL ID sequences to avoid sequence errors on new records
        if (config('database.default') === 'pgsql') {
            $this->info("Resetting PostgreSQL primary key sequences...");
            
            $maxOrderId = DB::table('orden_produccions')->max('id');
            if ($maxOrderId) {
                DB::select("SELECT setval('orden_produccions_id_seq', ?, true)", [$maxOrderId]);
                $this->info("Reset sequence for 'orden_produccions' to: {$maxOrderId}");
            }

            $maxUserId = DB::table('users')->max('id');
            if ($maxUserId) {
                DB::select("SELECT setval('users_id_seq', ?, true)", [$maxUserId]);
                $this->info("Reset sequence for 'users' to: {$maxUserId}");
            }
        }

        // Show counts
        $postgresUsersCount = User::count();
        $postgresOrdersCount = OrdenProduccion::count();

        $this->info("--------------------------------------------------");
        $this->info("Migration completed successfully!");
        $this->info("SQLite counts      -> Users: {$sqliteUsersCount}, Orders: {$sqliteOrdersCount}");
        $this->info("PostgreSQL counts  -> Users: {$postgresUsersCount}, Orders: {$postgresOrdersCount}");
        $this->info("Migrated records   -> Users: {$migratedUsers}, Orders: {$migratedOrders}");
        $this->info("Skipped (duplicates)-> Users: {$skippedUsers}, Orders: {$skippedOrders}");
        $this->info("--------------------------------------------------");

        return Command::SUCCESS;
    }
}
