<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Company;
use Faker\Factory as Faker;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $index = 0;

        $companies = [
            [
                'uuid' => Str::uuid7()->toString(),
                'name' => 'Acme Corporation',
                'description' => 'A leading provider of innovative solutions and services.',
                'phone' => $faker->phoneNumber(),
                'address' => '123 Main Street, Suite 100',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'email' => 'info@acmecorp.com',
                'contact_person' => 'John Doe',
                'contact_email' => 'john.doe@acmecorp.com',
                'contact_phone' => $faker->phoneNumber(),
                'is_active' => true,
            ],
            [
                'uuid' => Str::uuid7()->toString(),
                'name' => 'TechVentures Inc.',
                'description' => 'Specializing in cutting-edge technology and software development.',
                'phone' => $faker->phoneNumber(),
                'address' => '456 Innovation Drive',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94105',
                'country' => 'USA',
                'email' => 'contact@techventures.com',
                'contact_person' => 'Jane Smith',
                'contact_email' => 'jane.smith@techventures.com',
                'contact_phone' => $faker->phoneNumber(),
                'is_active' => true,
            ],
            [
                'uuid' => Str::uuid7()->toString(),
                'name' => 'Global Solutions Ltd.',
                'description' => 'International consulting and business services.',
                'phone' => $faker->phoneNumber(),
                'address' => '789 Business Park Avenue',
                'city' => 'London',
                'state' => 'England',
                'postal_code' => 'EC2A 4NE',
                'country' => 'UK',
                'email' => 'hello@globalsolutions.com',
                'contact_person' => 'Robert Johnson',
                'contact_email' => 'robert.johnson@globalsolutions.com',
                'contact_phone' => $faker->phoneNumber(),
                'is_active' => true,
            ],
            [
                'uuid' => Str::uuid7()->toString(),
                'name' => 'StartUp Hub',
                'description' => 'Incubator and accelerator for emerging businesses.',
                'phone' => $faker->phoneNumber(),
                'address' => '321 Entrepreneur Lane',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'USA',
                'email' => 'info@startuphub.io',
                'contact_person' => 'Sarah Williams',
                'contact_email' => 'sarah.williams@startuphub.io',
                'contact_phone' => $faker->phoneNumber(),
                'is_active' => true,
            ],
            [
                'uuid' => Str::uuid7()->toString(),
                'name' => 'Legacy Enterprises',
                'description' => 'Traditional business with modern approach.',
                'phone' => $faker->phoneNumber(),
                'address' => '555 Heritage Boulevard',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country' => 'USA',
                'email' => 'contact@legacyenterprises.com',
                'contact_person' => 'Michael Brown',
                'contact_email' => 'michael.brown@legacyenterprises.com',
                'contact_phone' => $faker->phoneNumber(),
                'is_active' => false, // Example of inactive company
            ],
        ];

        foreach ($companies as $company) {
            Company::factory()->create($company);
        }
    }
}

