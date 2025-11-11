<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            'Côte-d\'Or',
            'Doubs',
            'Jura',
            'Nièvre',
            'Haute-Saône',
            'Saône-et-Loire',
            'Yonne',
            'Territoire de Belfort',
        ];

        foreach ($departments as $departmentName) {
            \App\Models\Department::updateOrCreate(
                ['name' => $departmentName]
            );
        }

        // Update existing regions with their departments
        $regionDepartments = [
            'Dijon' => 'Côte-d\'Or',
            'Beaune' => 'Côte-d\'Or',
            'Besançon' => 'Doubs',
            'Montbéliard' => 'Doubs',
            'Dole' => 'Jura',
            'Lons-le-Saunier' => 'Jura',
            'Nevers' => 'Nièvre',
            'Vesoul' => 'Haute-Saône',
            'Chalon-sur-Saône' => 'Saône-et-Loire',
            'Mâcon' => 'Saône-et-Loire',
            'Le Creusot' => 'Saône-et-Loire',
            'Auxerre' => 'Yonne',
            'Sens' => 'Yonne',
            'Belfort' => 'Territoire de Belfort',
        ];

        foreach ($regionDepartments as $cityName => $departmentName) {
            $department = \App\Models\Department::where('name', $departmentName)->first();
            if ($department) {
                \App\Models\Region::where('name', $cityName)
                    ->update(['department_id' => $department->id]);
            }
        }
    }
}
