<?php

namespace Modules\Lumasachi\Tests\Unit\database\seeders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Lumasachi\app\Models\Company;
use Modules\Lumasachi\database\seeders\CompanySeeder;
use PHPUnit\Framework\Attributes\Test;

final class CompanySeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the seeder creates companies correctly.
     */
    #[Test]
    public function it_checks_if_seeder_creates_companies(): void
    {
        $this->seed(CompanySeeder::class);

        $this->assertDatabaseHas('companies', ['name' => 'Acme Corporation']);
        $this->assertDatabaseHas('companies', ['name' => 'TechVentures Inc.']);
        $this->assertDatabaseHas('companies', ['name' => 'Global Solutions Ltd.']);
        $this->assertDatabaseHas('companies', ['name' => 'StartUp Hub']);
        $this->assertDatabaseHas('companies', ['name' => 'Legacy Enterprises']);

        // Ensure that inactive companies exist
        $this->assertDatabaseHas('companies', [
            'name' => 'Legacy Enterprises',
            'is_active' => false
        ]);
    }
}

