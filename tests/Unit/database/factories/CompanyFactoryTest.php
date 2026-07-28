<?php

declare(strict_types=1);

use App\Models\Company;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if factory creates valid company', function () {
    $company = Company::factory()->create();

    expect($company)->toBeInstanceOf(Company::class);
    $this->assertDatabaseHas('companies', [
        'uuid' => $company->uuid,
        'name' => $company->name,
    ]);
});
it('checks if factory generates all required fields', function () {
    $company = Company::factory()->make();

    expect($company->name)->not->toBeNull();
    expect($company->email)->not->toBeNull();
    expect($company->phone)->not->toBeNull();
    expect($company->address)->not->toBeNull();
    expect($company->city)->not->toBeNull();
    expect($company->state)->not->toBeNull();
    expect($company->postal_code)->not->toBeNull();
    expect($company->country)->not->toBeNull();
});
it('checks if optional fields', function () {
    $optionalFields = [
        'description',
        'website',
        'logo',
        'tax_id',
        'contact_person',
        'contact_email',
        'contact_phone',
        'notes',
        'settings',
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
        expect($fieldStats[$field]['filled'])->toBeGreaterThan(0);
        expect($fieldStats[$field]['null'])->toBeGreaterThan(0);
    }
});
it('checks if optional field probabilities', function () {
    $expectedProbabilities = [
        'description' => 0.8,
        'website' => 0.7,
        'logo' => 0.6,
        'tax_id' => 0.7,
        'contact_person' => 0.8,
        'contact_email' => 0.8,
        'contact_phone' => 0.8,
        'notes' => 0.5,
        'settings' => 0.9,
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

        expect($actualProbability)->toBeGreaterThanOrEqual($expectedProbability - $tolerance);
        expect($actualProbability)->toBeLessThanOrEqual($expectedProbability + $tolerance);
    }
});
it('checks if factory state methods for optional fields', function () {
    // Test withoutWebsite state
    $companyWithoutWebsite = Company::factory()->withoutWebsite()->make();
    expect($companyWithoutWebsite->website)->toBeNull();

    // Test withoutLogo state
    $companyWithoutLogo = Company::factory()->withoutLogo()->make();
    expect($companyWithoutLogo->logo)->toBeNull();

    // Test minimal state - all optional fields should be null
    $minimalCompany = Company::factory()->minimal()->make();
    expect($minimalCompany->description)->toBeNull();
    expect($minimalCompany->website)->toBeNull();
    expect($minimalCompany->logo)->toBeNull();
    expect($minimalCompany->tax_id)->toBeNull();
    expect($minimalCompany->contact_person)->toBeNull();
    expect($minimalCompany->contact_email)->toBeNull();
    expect($minimalCompany->contact_phone)->toBeNull();
    expect($minimalCompany->notes)->toBeNull();
    expect($minimalCompany->settings)->toBeNull();

    // Test complete state - all optional fields should be filled
    $completeCompany = Company::factory()->complete()->make();
    expect($completeCompany->description)->not->toBeNull();
    expect($completeCompany->notes)->not->toBeNull();
    expect($completeCompany->settings)->not->toBeNull();
    expect($completeCompany->settings)->toBeArray();
    expect($completeCompany->settings)->toHaveKey('currency');
    expect($completeCompany->settings)->toHaveKey('date_format');
    expect($completeCompany->settings)->toHaveKey('time_format');
});
