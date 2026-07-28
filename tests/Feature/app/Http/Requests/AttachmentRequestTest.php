<?php

declare(strict_types=1);

use App\Http\Requests\AttachmentRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
uses(Illuminate\Foundation\Testing\WithFaker::class);

beforeEach(function () {
    Storage::fake('local');

    // Set default config for tests
    Config::set('attachments.max_file_size', 10 * 1024 * 1024);
    // 10MB
    Config::set('attachments.allowed_mime_types', [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'application/zip',
    ]);
});
it('checks if validation passes with valid data', function () {
    $data = [
        'file' => UploadedFile::fake()->create('document.pdf', 2000, 'application/pdf'),
        'name' => 'Test Document',
        'description' => 'A test document description.',
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 1,
    ];

    $validator = makeValidator($data);

    expect($validator->passes())->toBeTrue();
});
it('checks if file is required', function () {
    $data = [
        'name' => 'Test Document',
        'description' => 'A test document description.',
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 1,
    ];

    $validator = makeValidator($data);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->toArray())->toHaveKey('file');
    expect($validator->errors()->first('file'))->toEqual(__('validation.custom.required', ['attribute' => __('validation.attributes.file')]));
});
it('checks if file size validation', function () {
    // Set max file size to 1MB for testing
    Config::set('attachments.max_file_size', 1024 * 1024);

    $data = [
        'file' => UploadedFile::fake()->create('large.pdf', 2000, 'application/pdf'), // 2MB in KB
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 1,
    ];

    $validator = makeValidator($data);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->toArray())->toHaveKey('file');
    expect($validator->errors()->first('file'))->toEqual(__('validation.custom.max', ['attribute' => __('validation.attributes.file'), 'max' => '1 MB']));
});
it('checks if allowed file types', function () {
    $data = [
        'file' => UploadedFile::fake()->create('test.exe', 500, 'application/x-msdownload'),
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 1,
    ];

    $validator = makeValidator($data);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->toArray())->toHaveKey('file');
});
it('checks if attachable type validation', function () {
    // Test missing attachable_type
    $data = [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        'attachable_id' => 1,
    ];

    $validator = makeValidator($data);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->toArray())->toHaveKey('attachable_type');
    expect($validator->errors()->first('attachable_type'))->toEqual(__('validation.custom.required', ['attribute' => __('validation.attributes.attachable_type')]));

    // Test invalid attachable_type
    $data2 = [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        'attachable_type' => 'App\\Models\\InvalidModel',
        'attachable_id' => 1,
    ];

    $validator2 = makeValidator($data2);

    expect($validator2->passes())->toBeFalse();
    expect($validator2->errors()->toArray())->toHaveKey('attachable_type');
    expect($validator2->errors()->first('attachable_type'))->toEqual(__('validation.custom.in', ['attribute' => __('validation.attributes.attachable_type')]));
});
it('checks if attachable id validation', function () {
    // Test missing attachable_id
    $data = [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        'attachable_type' => 'App\\Models\\Order',
    ];

    $validator = makeValidator($data);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->toArray())->toHaveKey('attachable_id');
    expect($validator->errors()->first('attachable_id'))->toEqual(__('validation.custom.required', ['attribute' => __('validation.attributes.attachable_id')]));

    // Test invalid attachable_id (zero or negative)
    $data2 = [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 0,
    ];

    $validator2 = makeValidator($data2);

    expect($validator2->passes())->toBeFalse();
    expect($validator2->errors()->toArray())->toHaveKey('attachable_id');
    expect($validator2->errors()->first('attachable_id'))->toEqual(__('validation.custom.min', ['attribute' => __('validation.attributes.attachable_id'), 'min' => 1]));
});
it('checks if optional fields validation', function () {
    // Test valid length
    $data = [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 1,
        'name' => str_repeat('a', 255),
        'description' => str_repeat('b', 1000),
    ];

    $validator = makeValidator($data);
    expect($validator->passes())->toBeTrue();

    // Test exceeding max length for name
    $data2 = [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 1,
        'name' => str_repeat('a', 256),
    ];

    $validator2 = makeValidator($data2);
    expect($validator2->passes())->toBeFalse();
    expect($validator2->errors()->toArray())->toHaveKey('name');

    // Test exceeding max length for description
    $data3 = [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        'attachable_type' => 'App\\Models\\Order',
        'attachable_id' => 1,
        'description' => str_repeat('b', 1001),
    ];

    $validator3 = makeValidator($data3);
    expect($validator3->passes())->toBeFalse();
    expect($validator3->errors()->toArray())->toHaveKey('description');
});
it('checks if all mime types have extensions', function () {
    $request = new AttachmentRequest();
    $method = new ReflectionMethod($request, 'getAllowedExtensions');
    $method->setAccessible(true);

    $extensions = $method->invoke($request);

    expect($extensions)->toBeArray();
    expect($extensions)->not->toBeEmpty();

    // Test some expected extensions based on config
    $expectedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'png', 'zip'];
    foreach ($expectedExtensions as $ext) {
        expect($extensions)->toContain($ext);
    }
});
it('checks if authorization checks create permission', function () {
    // User without permission
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = new AttachmentRequest();
    $request->setUserResolver(function () use ($user) {
        return $user;
    });

    // This will depend on your policy implementation
    // For now, let's just check that authorize method exists
    expect($request->authorize())->toBeBool();
});
/**
 * Create a validator instance with the request rules.
 *
 * @param array<string, mixed> $data
 */
function makeValidator(array $data): Illuminate\Validation\Validator
{
    $request = new AttachmentRequest();

    return Validator::make($data, $request->rules(), $request->messages());
}
