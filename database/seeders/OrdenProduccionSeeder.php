<?php

namespace Database\Seeders;

use App\Models\OrdenProduccion;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class OrdenProduccionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Branding: Entrega hoy -> URGENTE 🔥
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-2026-001',
            'proyecto' => 'Backing Prensa Lanzamiento',
            'presupuestista' => 'Alejandro Ramos',
            'cliente' => 'Tigo',
            'marca' => 'Tigo Business',
            'fecha_entrega' => Carbon::today()->format('Y-m-d'),
            'hora_entrega' => '10:00:00',
            'entregar_a' => 'Instaladores',
            'lugar_instalacion' => 'Hotel Real Intercontinental, Salón Roble',
            'fecha_instalacion' => Carbon::today()->format('Y-m-d'),
            'hora_instalacion' => '07:30:00',
            'fecha_desinstalacion' => Carbon::today()->addDay()->format('Y-m-d'),
            'hora_desinstalacion' => '22:00:00',
            'brief' => null,
            'lider_produccion' => 'Carlos Mendoza',
            'estado' => 'En proceso',
        ]);

        // 2. Branding: Entrega en 2 días -> PRÓXIMA 🔥
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-2026-002',
            'proyecto' => 'Rotulación de Flota vehicular',
            'presupuestista' => 'Sofía Alvarado',
            'cliente' => 'Cervecería Centro Americana',
            'marca' => 'Gallo',
            'fecha_entrega' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'hora_entrega' => '17:00:00',
            'entregar_a' => 'Bodega',
            'brief' => null,
            'lider_produccion' => null,
            'estado' => 'Pendiente',
        ]);

        // 3. Branding: Entrega en 6 días -> NORMAL
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-2026-003',
            'proyecto' => 'Letrero Acrílico Luminoso',
            'presupuestista' => 'Sofía Alvarado',
            'cliente' => 'Banco Industrial',
            'marca' => 'BI Facil',
            'fecha_entrega' => Carbon::today()->addDays(6)->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'brief' => null,
            'lider_produccion' => 'Julio Estrada',
            'estado' => 'Terminado', // Avance should set to 100%
        ]);

        // 4. Promocional: Entrega mañana -> PRÓXIMA 🔥
        OrdenProduccion::create([
            'categoria' => 'Promocional',
            'numero_op' => 'OP-2026-004',
            'proyecto' => 'Kits Promocionales Activación',
            'presupuestista' => 'Alejandro Ramos',
            'cliente' => 'PepsiCo',
            'marca' => 'Pepsi',
            'fecha_entrega' => Carbon::today()->addDay()->format('Y-m-d'),
            'hora_entrega' => '08:30:00',
            'entregar_a' => 'Cliente',
            'brief' => null,
            'lider_produccion' => 'Marcos Gómez',
            'estado' => 'En proceso', // Avance should set to 50%
        ]);

        // 5. Promocional: Entrega en 10 días -> NORMAL
        OrdenProduccion::create([
            'categoria' => 'Promocional',
            'numero_op' => 'OP-2026-005',
            'proyecto' => 'Toldos y Displays Araña',
            'presupuestista' => 'Lorena Santos',
            'cliente' => 'Nestlé',
            'marca' => 'Nescafé',
            'fecha_entrega' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'hora_entrega' => '15:00:00',
            'entregar_a' => 'Instaladores',
            'lugar_instalacion' => 'Supermercado La Torre, Pradera',
            'fecha_instalacion' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'hora_instalacion' => '09:00:00',
            'fecha_desinstalacion' => Carbon::today()->addDays(12)->format('Y-m-d'),
            'hora_desinstalacion' => '18:00:00',
            'brief' => null,
            'lider_produccion' => null,
            'estado' => 'Pendiente',
        ]);
    }
}
