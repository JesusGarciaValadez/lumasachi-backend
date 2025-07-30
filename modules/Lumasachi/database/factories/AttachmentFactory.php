<?php

namespace Modules\Lumasachi\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use App\Models\User;

final class AttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Attachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Define possible attachable models
        $attachableModels = [
            Order::class,
            OrderHistory::class,
        ];

        // Pick a random attachable type
        $attachableType = $this->faker->randomElement($attachableModels);

        // Define possible file types with their extensions and mime types
        $fileTypes = [
            'image' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
                'mimeTypes' => Attachment::IMAGE_MIME_TYPES,
            ],
            'document' => [
                'extensions' => ['doc', 'docx', 'txt'],
                'mimeTypes' => [
                    Attachment::MIME_DOC,
                    Attachment::MIME_DOCX,
                    Attachment::MIME_TXT,
                ],
            ],
            'pdf' => [
                'extensions' => ['pdf'],
                'mimeTypes' => [Attachment::MIME_PDF],
            ],
            'spreadsheet' => [
                'extensions' => ['xls', 'xlsx', 'csv'],
                'mimeTypes' => Attachment::SPREADSHEET_MIME_TYPES,
            ],
            'presentation' => [
                'extensions' => ['ppt', 'pptx'],
                'mimeTypes' => Attachment::PRESENTATION_MIME_TYPES,
            ],
            'archive' => [
                'extensions' => ['zip', 'rar'],
                'mimeTypes' => [Attachment::MIME_ZIP, Attachment::MIME_RAR],
            ],
        ];

        // Pick a random file type
        $fileTypeKey = $this->faker->randomElement(array_keys($fileTypes));
        $fileType = $fileTypes[$fileTypeKey];

        // Pick random extension and mime type
        $extension = $this->faker->randomElement($fileType['extensions']);
        $mimeType = $this->faker->randomElement($fileType['mimeTypes']);

        // Generate file name
        $fileName = $this->faker->word() . '_' . time() . '.' . $extension;

        // Generate file path (simulating folder structure)
        $year = date('Y');
        $month = date('m');
        $filePath = "attachments/{$year}/{$month}/" . $fileName;

        // Generate file size (between 1KB and 10MB)
        $fileSize = $this->faker->numberBetween(1024, 10485760);

        return [
            'attachable_type' => $attachableType,
            'attachable_id' => function () use ($attachableType) {
                return $attachableType::factory()->create()->id;
            },
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Configure the factory to create an image attachment
     */
    public function image(): static
    {
        return $this->state(function (array $attributes) {
            $extension = $this->faker->randomElement(['jpg', 'png', 'gif', 'webp']);
            $mimeType = $this->faker->randomElement(Attachment::IMAGE_MIME_TYPES);
            $fileName = $this->faker->word() . '_' . time() . '.' . $extension;
            $year = date('Y');
            $month = date('m');

            return [
                'file_name' => $fileName,
                'file_path' => "attachments/{$year}/{$month}/" . $fileName,
                'mime_type' => $mimeType,
                'file_size' => $this->faker->numberBetween(10240, 5242880), // 10KB to 5MB
            ];
        });
    }

    /**
     * Configure the factory to create a PDF attachment
     */
    public function pdf(): static
    {
        return $this->state(function (array $attributes) {
            $fileName = $this->faker->word() . '_document_' . time() . '.pdf';
            $year = date('Y');
            $month = date('m');

            return [
                'file_name' => $fileName,
                'file_path' => "attachments/{$year}/{$month}/" . $fileName,
                'mime_type' => Attachment::MIME_PDF,
                'file_size' => $this->faker->numberBetween(51200, 10485760), // 50KB to 10MB
            ];
        });
    }

    /**
     * Configure the factory to create a document attachment
     */
    public function document(): static
    {
        return $this->state(function (array $attributes) {
            $types = [
                ['ext' => 'docx', 'mime' => Attachment::MIME_DOCX],
                ['ext' => 'doc', 'mime' => Attachment::MIME_DOC],
                ['ext' => 'txt', 'mime' => Attachment::MIME_TXT],
            ];

            $type = $this->faker->randomElement($types);
            $fileName = $this->faker->word() . '_' . time() . '.' . $type['ext'];
            $year = date('Y');
            $month = date('m');

            return [
                'file_name' => $fileName,
                'file_path' => "attachments/{$year}/{$month}/" . $fileName,
                'mime_type' => $type['mime'],
                'file_size' => $this->faker->numberBetween(5120, 2097152), // 5KB to 2MB
            ];
        });
    }

    /**
     * Configure the factory to create a spreadsheet attachment
     */
    public function spreadsheet(): static
    {
        return $this->state(function (array $attributes) {
            $types = [
                ['ext' => 'xlsx', 'mime' => Attachment::MIME_XLSX],
                ['ext' => 'xls', 'mime' => Attachment::MIME_XLS],
                ['ext' => 'csv', 'mime' => Attachment::MIME_CSV],
            ];

            $type = $this->faker->randomElement($types);
            $fileName = $this->faker->word() . '_data_' . time() . '.' . $type['ext'];
            $year = date('Y');
            $month = date('m');

            return [
                'file_name' => $fileName,
                'file_path' => "attachments/{$year}/{$month}/" . $fileName,
                'mime_type' => $type['mime'],
                'file_size' => $this->faker->numberBetween(10240, 5242880), // 10KB to 5MB
            ];
        });
    }

    /**
     * Configure the factory to create a small file
     */
    public function small(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'file_size' => $this->faker->numberBetween(1024, 102400), // 1KB to 100KB
            ];
        });
    }

    /**
     * Configure the factory to create a large file
     */
    public function large(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'file_size' => $this->faker->numberBetween(10485760, 52428800), // 10MB to 50MB
            ];
        });
    }

    /**
     * Configure the factory for a specific attachable model
     */
    public function forOrder(Order $order): static
    {
        return $this->state(function (array $attributes) use ($order) {
            return [
                'attachable_type' => Order::class,
                'attachable_id' => $order->id,
            ];
        });
    }

    /**
     * Configure the factory for a specific attachable model
     */
    public function forOrderHistory(OrderHistory $orderHistory): static
    {
        return $this->state(function (array $attributes) use ($orderHistory) {
            return [
                'attachable_type' => OrderHistory::class,
                'attachable_id' => $orderHistory->id,
            ];
        });
    }
}
