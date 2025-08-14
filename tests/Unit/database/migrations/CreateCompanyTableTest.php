<?php

namespace Tests\Unit\database\migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CreateCompanyTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the companies table exists after migration.
     */
    #[Test]
    public function it_checks_if_companies_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('companies'));
    }

    /**
     * Test that the companies table has all required columns.
     */
    #[Test]
    public function it_checks_if_companies_table_has_all_required_columns(): void
    {
        $expectedColumns = [
            'uuid',
            'name',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'website',
            'logo',
            'tax_id',
            'contact_person',
            'contact_email',
            'contact_phone',
            'notes',
            'description',
            'is_active',
            'settings',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('companies', $column),
                "Column '{$column}' does not exist in companies table"
            );
        }
    }
}

