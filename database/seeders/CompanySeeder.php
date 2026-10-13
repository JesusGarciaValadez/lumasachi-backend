<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        $companies = [
            ['name' => 'Acme Corporation', 'is_active' => true],
            ['name' => 'TechVentures Inc.', 'is_active' => true],
            ['name' => 'Global Solutions Ltd.', 'is_active' => true],
            ['name' => 'StartUp Hub', 'is_active' => true],
            ['name' => 'Legacy Enterprises', 'is_active' => false],
        ];

        $centralConnectionName = (string)config('tenancy.database.central_connection', config('database.default'));
        $usesLegacyCompanySchema = Schema::connection($centralConnectionName)->hasColumn('companies', 'email');

        foreach ($companies as $company) {
            $attributes = [
                ...$company,
            ];

            if ($usesLegacyCompanySchema) {
                DB::connection($centralConnectionName)->table('companies')->insert([
                    'uuid' => Str::uuid7()->toString(),
                    ...$attributes,
                    'email' => Str::slug($company['name']) . '@example.test',
                    'phone' => '5550000000',
                    'address' => 'Test address',
                    'city' => 'Test city',
                    'state' => 'Test state',
                    'postal_code' => '00000',
                    'country' => 'Test country',
                ]);

                continue;
            }

            Company::query()->create($attributes);
        }
    }
}
