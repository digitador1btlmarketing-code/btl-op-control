<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->environment('production') || env('APP_ENV') === 'production') {
            return;
        }

        $orders = [
            [
                'categoria' => 'Branding',
                'numero_op' => 'OP-2026-001',
                'proyecto' => 'Backing Prensa Lanzamiento',
                'presupuestista' => 'Alejandro Ramos',
                'cliente' => 'Tigo',
                'marca' => 'Tigo Business',
                'fecha_entrega' => '2026-06-21',
                'hora_entrega' => '10:00:00',
                'entregar_a' => 'Instaladores',
                'lugar_instalacion' => 'Hotel Real Intercontinental, Salón Roble',
                'fecha_instalacion' => '2026-06-21',
                'hora_instalacion' => '07:30:00',
                'fecha_desinstalacion' => '2026-06-22',
                'hora_desinstalacion' => '22:00:00',
                'brief' => null,
                'lider_produccion' => 'Carlos Mendoza',
                'estado' => 'Terminado',
                'avance' => 100,
            ],
            [
                'categoria' => 'Branding',
                'numero_op' => 'OP-2026-002',
                'proyecto' => 'Rotulación de Flota vehicular',
                'presupuestista' => 'Sofía Alvarado',
                'cliente' => 'Cervecería Centro Americana',
                'marca' => 'Gallo',
                'fecha_entrega' => '2026-06-23',
                'hora_entrega' => '17:00:00',
                'entregar_a' => 'Bodega',
                'lugar_instalacion' => null,
                'fecha_instalacion' => null,
                'hora_instalacion' => null,
                'fecha_desinstalacion' => null,
                'hora_desinstalacion' => null,
                'brief' => null,
                'lider_produccion' => 'Leo Messi',
                'estado' => 'En proceso',
                'avance' => 50,
            ],
            [
                'categoria' => 'Branding',
                'numero_op' => 'OP-2026-003',
                'proyecto' => 'Letrero Acrílico Luminoso',
                'presupuestista' => 'Sofía Alvarado',
                'cliente' => 'Banco Industrial',
                'marca' => 'BI Facil',
                'fecha_entrega' => '2026-06-27',
                'hora_entrega' => '12:00:00',
                'entregar_a' => 'Cliente',
                'lugar_instalacion' => null,
                'fecha_instalacion' => null,
                'hora_instalacion' => null,
                'fecha_desinstalacion' => null,
                'hora_desinstalacion' => null,
                'brief' => null,
                'lider_produccion' => 'Julio Estrada',
                'estado' => 'Terminado',
                'avance' => 100,
            ],
            [
                'categoria' => 'Promocional',
                'numero_op' => 'OP-2026-004',
                'proyecto' => 'Kits Promocionales Activación',
                'presupuestista' => 'Alejandro Ramos',
                'cliente' => 'PepsiCo',
                'marca' => 'Pepsi',
                'fecha_entrega' => '2026-06-22',
                'hora_entrega' => '08:30:00',
                'entregar_a' => 'Cliente',
                'lugar_instalacion' => null,
                'fecha_instalacion' => null,
                'hora_instalacion' => null,
                'fecha_desinstalacion' => null,
                'hora_desinstalacion' => null,
                'brief' => null,
                'lider_produccion' => 'Marcos Gómez',
                'estado' => 'En proceso',
                'avance' => 50,
            ],
            [
                'categoria' => 'Promocional',
                'numero_op' => 'OP-2026-005',
                'proyecto' => 'Toldos y Displays Araña',
                'presupuestista' => 'Lorena Santos',
                'cliente' => 'Nestlé',
                'marca' => 'Nescafé',
                'fecha_entrega' => '2026-07-01',
                'hora_entrega' => '15:00:00',
                'entregar_a' => 'Instaladores',
                'lugar_instalacion' => 'Supermercado La Torre, Pradera',
                'fecha_instalacion' => '2026-07-01',
                'hora_instalacion' => '09:00:00',
                'fecha_desinstalacion' => '2026-07-03',
                'hora_desinstalacion' => '18:00:00',
                'brief' => null,
                'lider_produccion' => 'Mario Rivas',
                'estado' => 'Terminado',
                'avance' => 100,
            ],
            [
                'categoria' => 'Branding',
                'numero_op' => 'OP-2173',
                'proyecto' => 'Maggi One Food',
                'presupuestista' => 'Juan Perez',
                'cliente' => 'Nestle',
                'marca' => 'Maggi',
                'fecha_entrega' => '2026-06-22',
                'hora_entrega' => '10:00',
                'entregar_a' => 'Instaladores',
                'lugar_instalacion' => 'Multicentro Las Americas',
                'fecha_instalacion' => '2026-06-23',
                'hora_instalacion' => '08:00',
                'fecha_desinstalacion' => '2026-06-24',
                'hora_desinstalacion' => '18:00',
                'brief' => 'briefs/fq20JpF99Cocfhq0SeWKLfLo3hYoFZgZC2IeNm3a.jpg',
                'lider_produccion' => 'Jose Lopez',
                'estado' => 'Terminado',
                'avance' => 100,
            ],
            [
                'categoria' => 'Branding',
                'numero_op' => 'OP-2175',
                'proyecto' => 'Colgate Mejor Equipo',
                'presupuestista' => 'Juan Perez',
                'cliente' => 'Colgate',
                'marca' => 'Colgate',
                'fecha_entrega' => '2026-06-30',
                'hora_entrega' => '09:00',
                'entregar_a' => 'Bodega',
                'lugar_instalacion' => null,
                'fecha_instalacion' => null,
                'hora_instalacion' => null,
                'fecha_desinstalacion' => null,
                'hora_desinstalacion' => null,
                'brief' => null,
                'lider_produccion' => 'Mario Rivas',
                'estado' => 'Pendiente',
                'avance' => 0,
            ],
        ];

        foreach ($orders as $order) {
            if (DB::table('orden_produccions')->where('numero_op', $order['numero_op'])->count() === 0) {
                // Set default timestamps
                $order['created_at'] = now();
                $order['updated_at'] = now();
                DB::table('orden_produccions')->insert($order);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete seeded baseline test records
        DB::table('orden_produccions')->whereIn('numero_op', [
            'OP-2026-001', 'OP-2026-002', 'OP-2026-003', 'OP-2026-004', 'OP-2026-005', 'OP-2173', 'OP-2175'
        ])->delete();
    }
};
