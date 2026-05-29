<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zones = [
            ['district' => 'Cercado de Lima', 'cost_motorizado' => 12, 'cost_shalom' => 15],
            ['district' => 'Breña', 'cost_motorizado' => 14, 'cost_shalom' => 17],
            ['district' => 'Jesús María', 'cost_motorizado' => 14, 'cost_shalom' => 17],
            ['district' => 'La Victoria', 'cost_motorizado' => 11, 'cost_shalom' => 14],
            ['district' => 'Lince', 'cost_motorizado' => 14, 'cost_shalom' => 17],
            ['district' => 'Magdalena del Mar', 'cost_motorizado' => 15, 'cost_shalom' => 18],
            ['district' => 'Miraflores', 'cost_motorizado' => 15, 'cost_shalom' => 18],
            ['district' => 'Pueblo Libre', 'cost_motorizado' => 15, 'cost_shalom' => 18],
            ['district' => 'Rímac', 'cost_motorizado' => 11, 'cost_shalom' => 14],
            ['district' => 'San Borja', 'cost_motorizado' => 11, 'cost_shalom' => 14],
            ['district' => 'San Isidro', 'cost_motorizado' => 14, 'cost_shalom' => 17],
            ['district' => 'San Miguel', 'cost_motorizado' => 15, 'cost_shalom' => 18],
            ['district' => 'Santiago de Surco', 'cost_motorizado' => 12, 'cost_shalom' => 15],
            ['district' => 'Surquillo', 'cost_motorizado' => 13, 'cost_shalom' => 16],
            ['district' => 'Carabayllo', 'cost_motorizado' => 20, 'cost_shalom' => 25],
            ['district' => 'Comas', 'cost_motorizado' => 16, 'cost_shalom' => 20],
            ['district' => 'Independencia', 'cost_motorizado' => 15, 'cost_shalom' => 18],
            ['district' => 'Los Olivos', 'cost_motorizado' => 15, 'cost_shalom' => 18],
            ['district' => 'Puente Piedra', 'cost_motorizado' => 20, 'cost_shalom' => 25],
            ['district' => 'San Martín de Porres', 'cost_motorizado' => 15, 'cost_shalom' => 18],
            ['district' => 'Santa Rosa', 'cost_motorizado' => 35, 'cost_shalom' => 40],
            ['district' => 'Ancón', 'cost_motorizado' => 35, 'cost_shalom' => 40],
            ['district' => 'Ate', 'cost_motorizado' => 10, 'cost_shalom' => 13],
            ['district' => 'El Agustino', 'cost_motorizado' => 10, 'cost_shalom' => 13],
            ['district' => 'Lurigancho-Chosica', 'cost_motorizado' => 16, 'cost_shalom' => 20],
            ['district' => 'San Juan de Lurigancho', 'cost_motorizado' => 14, 'cost_shalom' => 17],
            ['district' => 'Santa Anita', 'cost_motorizado' => 10, 'cost_shalom' => 13],
            ['district' => 'Chaclacayo', 'cost_motorizado' => 16, 'cost_shalom' => 20],
            ['district' => 'Cieneguilla', 'cost_motorizado' => 30, 'cost_shalom' => 35],
            ['district' => 'Villa El Salvador', 'cost_motorizado' => 16, 'cost_shalom' => 20],
            ['district' => 'Villa María', 'cost_motorizado' => 16, 'cost_shalom' => 20],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::firstOrCreate(
                ['district' => $zone['district']],
                [
                    'cost_motorizado' => $zone['cost_motorizado'],
                    'cost_shalom' => $zone['cost_shalom'],
                ]
            );
        }
    }
}
