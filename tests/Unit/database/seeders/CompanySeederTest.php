<?php

declare(strict_types=1);

namespace Tests\Unit\database\seeders;

use Database\Seeders\CompanySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
            'is_active' => false,
        ]);
    }
}
