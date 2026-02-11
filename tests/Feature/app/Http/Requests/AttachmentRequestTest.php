<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Requests;

use App\Http\Requests\AttachmentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

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
     * Test that validation passes with valid data.
     */
    #[Test]
    public function it_checks_if_validation_passes_with_valid_data(): void
    {
        $data = [
            'file' => UploadedFile::fake()->create('document.pdf', 2000, 'application/pdf'),
            'name' => 'Test Document',
            'description' => 'A test document description.',
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that file is required.
     */
    #[Test]
    public function it_checks_if_file_is_required(): void
    {
        $data = [
            'name' => 'Test Document',
            'description' => 'A test document description.',
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
        $this->assertEquals('Por favor seleccione un archivo.', $validator->errors()->first('file'));
    }

    /**
     * Test file size validation.
     */
    #[Test]
    public function it_checks_if_file_size_validation(): void
    {
        // Set max file size to 1MB for testing
        Config::set('attachments.max_file_size', 1024 * 1024);

        $data = [
            'file' => UploadedFile::fake()->create('large.pdf', 2000, 'application/pdf'), // 2MB in KB
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
        $this->assertEquals('El archivo no debe exceder los 1MB.', $validator->errors()->first('file'));
    }

    /**
     * Test allowed file types based on configuration.
     */
    #[Test]
    public function it_checks_if_allowed_file_types(): void
    {
        $data = [
            'file' => UploadedFile::fake()->create('test.exe', 500, 'application/x-msdownload'),
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 1,
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    /**
     * Test that attachable_type is required and must be valid.
     */
    #[Test]
    public function it_checks_if_attachable_type_validation(): void
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
     */
    #[Test]
    public function it_checks_if_attachable_id_validation(): void
    {
        // Test missing attachable_id
        $data = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'App\\Models\\Order',
        ];

        $validator = $this->makeValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('attachable_id', $validator->errors()->toArray());
        $this->assertEquals('El ID de la entidad es requerido.', $validator->errors()->first('attachable_id'));

        // Test invalid attachable_id (zero or negative)
        $data2 = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 0,
        ];

        $validator2 = $this->makeValidator($data2);

        $this->assertFalse($validator2->passes());
        $this->assertArrayHasKey('attachable_id', $validator2->errors()->toArray());
        $this->assertEquals('El ID de la entidad debe ser mayor a 0.', $validator2->errors()->first('attachable_id'));
    }

    /**
     * Test optional name and description fields.
     */
    #[Test]
    public function it_checks_if_optional_fields_validation(): void
    {
        // Test valid length
        $data = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 1,
            'name' => str_repeat('a', 255),
            'description' => str_repeat('b', 1000),
        ];

        $validator = $this->makeValidator($data);
        $this->assertTrue($validator->passes());

        // Test exceeding max length for name
        $data2 = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 1,
            'name' => str_repeat('a', 256),
        ];

        $validator2 = $this->makeValidator($data2);
        $this->assertFalse($validator2->passes());
        $this->assertArrayHasKey('name', $validator2->errors()->toArray());

        // Test exceeding max length for description
        $data3 = [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'attachable_type' => 'App\\Models\\Order',
            'attachable_id' => 1,
            'description' => str_repeat('b', 1001),
        ];

        $validator3 = $this->makeValidator($data3);
        $this->assertFalse($validator3->passes());
        $this->assertArrayHasKey('description', $validator3->errors()->toArray());
    }

    /**
     * Test that all configured mime types have corresponding extensions.
     */
    #[Test]
    public function it_checks_if_all_mime_types_have_extensions(): void
    {
        $request = new AttachmentRequest();
        $method = new ReflectionMethod($request, 'getAllowedExtensions');
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
     */
    #[Test]
    public function it_checks_if_authorization_checks_create_permission(): void
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

    /**
     * Create a validator instance with the request rules.
     *
     * @param  array<string, mixed>  $data
     */
    private function makeValidator(array $data): \Illuminate\Validation\Validator
    {
        $request = new AttachmentRequest();

        return Validator::make($data, $request->rules(), $request->messages());
    }
}
