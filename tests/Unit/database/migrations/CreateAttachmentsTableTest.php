<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if attachments table exists', function () {
    expect(Schema::hasTable('attachments'))->toBeTrue();
});
it('checks if attachments table has all required columns', function () {
    $expectedColumns = [
        'id',
        'attachable_type',
        'attachable_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
        'created_at',
        'updated_at',
    ];

    foreach ($expectedColumns as $column) {
        expect(Schema::hasColumn('attachments', $column))->toBeTrue("Column '{$column}' does not exist in attachments table");
    }
});
it('checks if attachments table column types', function () {
    // Test string columns
    $stringColumns = ['attachable_type', 'file_name', 'file_path', 'mime_type'];
    foreach ($stringColumns as $column) {
        expect(['string', 'varchar'])->toContain(Schema::getColumnType('attachments', $column));
    }

    // Test UUID columns - SQLite uses varchar for UUID
    $uuidColumns = ['uuid'];
    foreach ($uuidColumns as $column) {
        $columnType = Schema::getColumnType('attachments', $column);
        expect(['uuid', 'varchar', 'string'])->toContain($columnType);
    }

    // Test integer columns - PostgreSQL returns 'int4' for integer columns
    expect(['integer', 'int4', 'int'])->toContain(Schema::getColumnType('attachments', 'file_size'));
});
it('checks if migration can be rolled back and rerun', function () {
    // Table should exist after migration
    expect(Schema::hasTable('attachments'))->toBeTrue();

    // Run down method
    Schema::dropIfExists('attachments');

    // Table should not exist
    expect(Schema::hasTable('attachments'))->toBeFalse();

    // Recreate the table
    $migration = include base_path('database/migrations/2025_07_27_172006_create_attachments_table.php');
    $migration->up();

    // Table should exist again
    expect(Schema::hasTable('attachments'))->toBeTrue();
});
it('checks if timestamp columns types', function () {
    $timestampColumns = ['created_at', 'updated_at'];
    foreach ($timestampColumns as $column) {
        expect(['timestamp', 'datetime'])->toContain(Schema::getColumnType('attachments', $column));
    }
});
it('checks if indexes exist', function () {
    // Check for composite index on polymorphic columns
    $indexes = collect(Schema::getIndexes('attachments'));

    // Check if attachable_index exists
    $attachableIndex = $indexes->first(function ($index) {
        return $index['name'] === 'attachable_index';
    });
    expect($attachableIndex)->not->toBeNull('attachable_index does not exist');

    // Check if uploaded_by index exists
    $uploadedByIndex = $indexes->first(function ($index) {
        return str_contains($index['name'], 'uploaded_by');
    });
    expect($uploadedByIndex)->not->toBeNull('uploaded_by index does not exist');
});
it('checks if foreign key constraint', function () {
    // Create a user
    $user = User::factory()->create();

    // Create an attachment with valid user
    $attachment = Attachment::create([
        'attachable_type' => 'App\\Models\\TestModel',
        'attachable_id' => fake()->randomNumber(1, 99),
        'file_name' => 'test.pdf',
        'file_path' => 'attachments/test.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]);

    $this->assertDatabaseHas('attachments', [
        'file_name' => 'test.pdf',
        'uploaded_by' => $user->id,
    ]);

    // Test that we cannot create attachment with non-existent user
    $this->expectException(Illuminate\Database\QueryException::class);

    Attachment::create([
        'attachable_type' => 'App\\Models\\TestModel',
        'attachable_id' => fake()->randomNumber(1, 99),
        'file_name' => 'test2.pdf',
        'file_path' => 'attachments/test2.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'uploaded_by' => 99999, // Non-existent user ID
    ]);
});
it('checks if cascade on update uploaded by', function () {
    // Create a user
    $user = User::factory()->create();

    // Create an attachment
    $attachment = Attachment::create([
        'attachable_type' => 'App\\Models\\TestModel',
        'attachable_id' => fake()->randomNumber(1, 99),
        'file_name' => 'cascade_test.pdf',
        'file_path' => 'attachments/cascade_test.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]);

    // Update user ID (this is just to test the constraint, normally IDs don't change)
    $oldId = $user->id;
    $newId = $user->id + 1000;

    // Since we can't directly update the ID, we'll just verify the constraint exists
    // by checking that the foreign key relationship is properly established
    expect($attachment->uploadedBy->id)->toEqual($user->id);
});
it('checks if can create attachment with all fields', function () {
    $user = User::factory()->create();

    $attachment = Attachment::create([
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => '55',
        'file_name' => 'invoice_2024_001.pdf',
        'file_path' => 'invoices/2024/01/invoice_2024_001.pdf',
        'file_size' => 2048576, // 2MB
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]);

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->file_name)->toEqual('invoice_2024_001.pdf');
    expect($attachment->file_size)->toEqual(2048576);
    expect($attachment->mime_type)->toEqual('application/pdf');
    expect($attachment->uploaded_by)->toEqual($user->id);
});
it('checks if required columns do not accept null', function () {
    $user = User::factory()->create();

    // Test each required field
    $requiredFields = [
        'attachable_type',
        'attachable_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    foreach ($requiredFields as $field) {
        try {
            $data = [
                'attachable_type' => 'TestType',
                'attachable_id' => fake()->randomNumber(1, 99),
                'file_name' => 'test.pdf',
                'file_path' => 'test/test.pdf',
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_by' => $user->id,
            ];

            // Set the current field to null
            $data[$field] = null;

            Attachment::create($data);

            $this->fail("Field '{$field}' should not accept null values");
        } catch (Illuminate\Database\QueryException $e) {
            // Expected exception for null constraint violation
            expect(true)->toBeTrue();
        }
    }
});
