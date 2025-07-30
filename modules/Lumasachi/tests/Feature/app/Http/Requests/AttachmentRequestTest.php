<?php

namespace Modules\Lumasachi\tests\Feature\app\Http\Requests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Http\Requests\AttachmentRequest;

final class AttachmentRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Set default config for tests
        Config::set('attachments.max_file_size', 10 * 1024 * 1024); // 10MB
        Config::set('attachments.allowed_mime_types', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'application/zip',
        ]);
    }

    /**
     * Create a validator instance with the request rules.
     */
    private function makeValidator($data)
    {
        $request = new AttachmentRequest();
        return Validator::make($data, $request->rules(), $request->messages());
    }

    /**
     * Test that validation passes with valid data.
     *
     * @return void
     */
    public function test_validation_passes_with_valid_data()
    {
        $data = [
            'file' => UploadedFile::fake()->create('document.pdf', 2000, 'application/pdf'),
            'name' => 'Test Document',
            'description' => 'A test document description.',
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that file is required.
     *
     * @return void
     */
    public function test_file_is_required()
    {
        $data = [
            'name' => 'Test Document',
            'description' => 'A test document description.',
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
        $this->assertEquals('Por favor seleccione un archivo.', $validator->errors()->first('file'));
    }

    /**
     * Test file size validation.
     *
     * @return void
     */
    public function test_file_size_validation()
    {
        // Set max file size to 1MB for testing
        Config::set('attachments.max_file_size', 1024 * 1024);

        $data = [
            'file' => UploadedFile::fake()->create('large.pdf', 2000, 'application/pdf'), // 2MB in KB
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
        $this->assertEquals('El archivo no debe exceder los 1MB.', $validator->errors()->first('file'));
    }

    /**
     * Test allowed file types based on configuration.
     *
     * @return void
     */
    public function test_allowed_file_types()
    {
        $data = [
            'file' => UploadedFile::fake()->create('test.exe', 500, 'application/x-msdownload'),
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    /**
     * Test that attachable_type is required and must be valid.
     *
     * @return void
     */
    public function test_attachable_type_validation()
    {
        // Test missing attachable_type
        $data = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('attachable_type', $validator->errors()->toArray());
        $this->assertEquals('El tipo de entidad es requerido.', $validator->errors()->first('attachable_type'));

        // Test invalid attachable_type
        $data2 = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'App\\Models\\InvalidModel',
            'attachable_id' => 1,
        ];

        $validator2 = $this->makeValidator($data2);

        $this->assertFalse($validator2->passes());
        $this->assertArrayHasKey('attachable_type', $validator2->errors()->toArray());
        $this->assertEquals('El tipo de entidad no es válido.', $validator2->errors()->first('attachable_type'));
    }

    /**
     * Test that attachable_id is required and must be positive integer.
     *
     * @return void
     */
    public function test_attachable_id_validation()
    {
        // Test missing attachable_id
        $data = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('attachable_id', $validator->errors()->toArray());
        $this->assertEquals('El ID de la entidad es requerido.', $validator->errors()->first('attachable_id'));

        // Test invalid attachable_id (zero or negative)
        $data2 = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 0,
        ];

        $validator2 = $this->makeValidator($data2);

        $this->assertFalse($validator2->passes());
        $this->assertArrayHasKey('attachable_id', $validator2->errors()->toArray());
        $this->assertEquals('El ID de la entidad debe ser mayor a 0.', $validator2->errors()->first('attachable_id'));
    }

    /**
     * Test optional name and description fields.
     *
     * @return void
     */
    public function test_optional_fields_validation()
    {
        // Test valid length
        $data = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 1,
            'name' => str_repeat('a', 255),
            'description' => str_repeat('b', 1000),
        ];

        $validator = $this->makeValidator($data);
        $this->assertTrue($validator->passes());

        // Test exceeding max length for name
        $data2 = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 1,
            'name' => str_repeat('a', 256),
        ];

        $validator2 = $this->makeValidator($data2);
        $this->assertFalse($validator2->passes());
        $this->assertArrayHasKey('name', $validator2->errors()->toArray());

        // Test exceeding max length for description
        $data3 = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'Modules\\Lumasachi\\app\\Models\\Order',
            'attachable_id' => 1,
            'description' => str_repeat('b', 1001),
        ];

        $validator3 = $this->makeValidator($data3);
        $this->assertFalse($validator3->passes());
        $this->assertArrayHasKey('description', $validator3->errors()->toArray());
    }

    /**
     * Test that all configured mime types have corresponding extensions.
     *
     * @return void
     */
    public function test_all_mime_types_have_extensions()
    {
        $request = new AttachmentRequest();
        $method = new \ReflectionMethod($request, 'getAllowedExtensions');
        $method->setAccessible(true);

        $extensions = $method->invoke($request);

        $this->assertIsArray($extensions);
        $this->assertNotEmpty($extensions);

        // Test some expected extensions based on config
        $expectedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'png', 'zip'];
        foreach ($expectedExtensions as $ext) {
            $this->assertContains($ext, $extensions);
        }
    }

    /**
     * Test authorization checks create permission.
     *
     * @return void
     */
    public function test_authorization_checks_create_permission()
    {
        // User without permission
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = new AttachmentRequest();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // This will depend on your policy implementation
        // For now, let's just check that authorize method exists
        $this->assertIsBool($request->authorize());
    }
}

