<?php

namespace Modules\Lumasachi\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Lumasachi\app\Models\Category;
use App\Models\User;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first user to use as creator/updater
        $user = User::first();
        
        if (!$user) {
            $user = User::factory()->create();
        }

        $categories = [
            [
                'name' => 'Mantenimiento',
                'description' => 'Trabajos de mantenimiento general y reparaciones',
                'color' => '#3B82F6',
                'sort_order' => 1,
            ],
            [
                'name' => 'Instalación',
                'description' => 'Instalación de nuevos equipos o sistemas',
                'color' => '#10B981',
                'sort_order' => 2,
            ],
            [
                'name' => 'Consultoría',
                'description' => 'Servicios de consultoría y asesoramiento',
                'color' => '#F59E0B',
                'sort_order' => 3,
            ],
            [
                'name' => 'Soporte',
                'description' => 'Soporte técnico y asistencia',
                'color' => '#8B5CF6',
                'sort_order' => 4,
            ],
            [
                'name' => 'Desarrollo',
                'description' => 'Desarrollo de software y aplicaciones',
                'color' => '#EC4899',
                'sort_order' => 5,
            ],
            [
                'name' => 'Capacitación',
                'description' => 'Capacitación y formación de personal',
                'color' => '#14B8A6',
                'sort_order' => 6,
            ],
            [
                'name' => 'Auditoría',
                'description' => 'Auditorías y evaluaciones',
                'color' => '#F97316',
                'sort_order' => 7,
            ],
            [
                'name' => 'Emergencia',
                'description' => 'Servicios de emergencia y urgencias',
                'color' => '#EF4444',
                'sort_order' => 8,
            ],
            [
                'name' => 'Otros',
                'description' => 'Otros servicios no categorizados',
                'color' => '#6B7280',
                'sort_order' => 99,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::create(array_merge($categoryData, [
                'is_active' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]));
        }
    }
}
