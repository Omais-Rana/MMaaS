<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regions = [
            // Major cities in Bourgogne-Franche-Comté
            ['name' => 'Dijon', 'postal_code' => '21000'],
            ['name' => 'Besançon', 'postal_code' => '25000'],
            ['name' => 'Belfort', 'postal_code' => '90000'],
            ['name' => 'Chalon-sur-Saône', 'postal_code' => '71100'],
            ['name' => 'Nevers', 'postal_code' => '58000'],
            ['name' => 'Auxerre', 'postal_code' => '89000'],
            ['name' => 'Mâcon', 'postal_code' => '71000'],
            ['name' => 'Sens', 'postal_code' => '89100'],
            ['name' => 'Le Creusot', 'postal_code' => '71200'],
            ['name' => 'Montbéliard', 'postal_code' => '25200'],
            ['name' => 'Beaune', 'postal_code' => '21200'],
            ['name' => 'Dole', 'postal_code' => '39100'],
            ['name' => 'Vesoul', 'postal_code' => '70000'],
            ['name' => 'Lons-le-Saunier', 'postal_code' => '39000'],
        ];

        foreach ($regions as $region) {
            \App\Models\Region::updateOrCreate(
                ['name' => $region['name']],
                ['postal_code' => $region['postal_code']]
            );
        }
    }
}
