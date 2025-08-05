<?php

namespace Modules\Lumasachi\Tests\Unit\database\factories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Lumasachi\app\Models\Company;

final class CompanyFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the factory creates a valid company.
     */
    public function test_factory_creates_valid_company(): void
    {
        $company = Company::factory()->create();

        $this->assertInstanceOf(Company::class, $company);
        $this->assertDatabaseHas('companies', [
            'uuid' => $company->uuid,
            'name' => $company->name,
        ]);
    }

    /**
     * Test that factory generates all required fields.
     */
    public function test_factory_generates_all_required_fields(): void
    {
        $company = Company::factory()->make();

        $this->assertNotNull($company->name);
        $this->assertNotNull($company->email);
        $this->assertNotNull($company->phone);
        $this->assertNotNull($company->address);
        $this->assertNotNull($company->city);
        $this->assertNotNull($company->state);
        $this->assertNotNull($company->postal_code);
        $this->assertNotNull($company->country);
    }

    /**
     * Test optional fields.
     */
    public function test_optional_fields(): void
    {
        $optionalFields = [
            'description',
            'website',
            'logo',
            'tax_id',
            'contact_person',
            'contact_email',
            'contact_phone',
            'notes',
            'settings'
        ];

        $fieldStats = [];
        foreach ($optionalFields as $field) {
            $fieldStats[$field] = ['filled' => 0, 'null' => 0];
        }

        // Generate multiple companies to test randomness of optional fields
        $sampleSize = 50;
        for ($i = 0; $i < $sampleSize; $i++) {
            $company = Company::factory()->make();

            foreach ($optionalFields as $field) {
                if ($company->$field !== null) {
                    $fieldStats[$field]['filled']++;
                } else {
                    $fieldStats[$field]['null']++;
                }
            }
        }

        // Verify that each optional field has both null and non-null values
        foreach ($optionalFields as $field) {
            $this->assertGreaterThan(
                0,
                $fieldStats[$field]['filled'],
                "Optional field '{$field}' should sometimes be filled"
            );
            $this->assertGreaterThan(
                0,
                $fieldStats[$field]['null'],
                "Optional field '{$field}' should sometimes be null"
            );
        }
    }

    /**
     * Test that optional field probabilities are roughly correct.
     */
    public function test_optional_field_probabilities(): void
    {
        $expectedProbabilities = [
            'description' => 0.8,
            'website' => 0.7,
            'logo' => 0.6,
            'tax_id' => 0.7,
            'contact_person' => 0.8,
            'contact_email' => 0.8,
            'contact_phone' => 0.8,
            'notes' => 0.5,
            'settings' => 0.9
        ];

        $sampleSize = 1000;
        $fieldCounts = [];
        foreach (array_keys($expectedProbabilities) as $field) {
            $fieldCounts[$field] = 0;
        }

        // Generate many companies to test probability distribution
        for ($i = 0; $i < $sampleSize; $i++) {
            $company = Company::factory()->make();

            foreach (array_keys($expectedProbabilities) as $field) {
                if ($company->$field !== null) {
                    $fieldCounts[$field]++;
                }
            }
        }

        // Check that actual probabilities are within acceptable range (±10%)
        foreach ($expectedProbabilities as $field => $expectedProbability) {
            $actualProbability = $fieldCounts[$field] / $sampleSize;
            $tolerance = 0.1;

            $this->assertGreaterThanOrEqual(
                $expectedProbability - $tolerance,
                $actualProbability,
                "Field '{$field}' fill rate ({$actualProbability}) is too low (expected ~{$expectedProbability})"
            );
            $this->assertLessThanOrEqual(
                $expectedProbability + $tolerance,
                $actualProbability,
                "Field '{$field}' fill rate ({$actualProbability}) is too high (expected ~{$expectedProbability})"
            );
        }
    }

    /**
     * Test factory state methods for optional fields.
     */
    public function test_factory_state_methods_for_optional_fields(): void
    {
        // Test withoutWebsite state
        $companyWithoutWebsite = Company::factory()->withoutWebsite()->make();
        $this->assertNull($companyWithoutWebsite->website);

        // Test withoutLogo state
        $companyWithoutLogo = Company::factory()->withoutLogo()->make();
        $this->assertNull($companyWithoutLogo->logo);

        // Test minimal state - all optional fields should be null
        $minimalCompany = Company::factory()->minimal()->make();
        $this->assertNull($minimalCompany->description);
        $this->assertNull($minimalCompany->website);
        $this->assertNull($minimalCompany->logo);
        $this->assertNull($minimalCompany->tax_id);
        $this->assertNull($minimalCompany->contact_person);
        $this->assertNull($minimalCompany->contact_email);
        $this->assertNull($minimalCompany->contact_phone);
        $this->assertNull($minimalCompany->notes);
        $this->assertNull($minimalCompany->settings);

        // Test complete state - all optional fields should be filled
        $completeCompany = Company::factory()->complete()->make();
        $this->assertNotNull($completeCompany->description);
        $this->assertNotNull($completeCompany->notes);
        $this->assertNotNull($completeCompany->settings);
        $this->assertIsArray($completeCompany->settings);
        $this->assertArrayHasKey('currency', $completeCompany->settings);
        $this->assertArrayHasKey('date_format', $completeCompany->settings);
        $this->assertArrayHasKey('time_format', $completeCompany->settings);
    }

}
