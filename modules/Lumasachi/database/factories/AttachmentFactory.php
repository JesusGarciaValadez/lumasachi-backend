<?php

namespace Modules\Lumasachi\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\User;
use Modules\Lumasachi\app\Models\Order;

class AttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \Modules\Lumasachi\app\Models\Attachment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $attachable = Order::factory()->create();

        return [
            'attachable_id' => $attachable->id,
            'attachable_type' => $attachable->getMorphClass(),
            'file_name' => $this->faker->word . '.' . $this->faker->fileExtension,
            'file_path' => 'attachments/' . date('Y/m/d') . '/' . Str::random(40) . '.' . $this->faker->fileExtension,
            'file_size' => $this->faker->numberBetween(100, 10000),
            'mime_type' => $this->faker->randomElement([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'text/plain',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
            'uploaded_by' => User::factory(),
        ];
    }

    public function pdf()
    {
        return $this->state(function (array $attributes) {
            return [
                'mime_type' => 'application/pdf',
                'file_name' => $this->faker->word . '.pdf',
            ];
        });
    }

    public function image()
    {
        return $this->state(function (array $attributes) {
            return [
                'mime_type' => 'image/png',
                'file_name' => $this->faker->word . '.png',
            ];
        });
    }

    public function spreadsheet()
    {
        return $this->state(function (array $attributes) {
            return [
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_name' => $this->faker->word . '.xlsx',
            ];
        });
    }
}
