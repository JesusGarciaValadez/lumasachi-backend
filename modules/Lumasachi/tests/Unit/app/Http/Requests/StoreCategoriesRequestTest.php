<?php

namespace Modules\Lumasachi\Tests\Unit\App\Http\Requests;

use Illuminate\Support\Facades\Validator;
use Modules\Lumasachi\app\Http\Requests\StoreCategoriesRequest;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class StoreCategoriesRequestTest extends TestCase
{
    private StoreCategoriesRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new StoreCategoriesRequest();
    }

    #[Test]
    public function authorize_should_return_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    #[Test]
    #[DataProvider('validationDataProvider')]
    public function test_validation_rules(array $data, bool $shouldPass): void
    {
        $validator = Validator::make($data, $this->request->rules());

        $this->assertEquals($shouldPass, $validator->passes());
    }

    public static function validationDataProvider(): array
    {
        return [
            'passes with valid data' => [
                'data' => [
                    'categories' => [
                        ['name' => 'Tech', 'description' => 'Tech stuff'],
                        ['name' => 'Health', 'is_active' => false],
                    ],
                ],
                'shouldPass' => true,
            ],
            'fails if categories is not an array' => [
                'data' => ['categories' => 'not-an-array'],
                'shouldPass' => false,
            ],
            'fails if a category name is missing' => [
                'data' => [
                    'categories' => [
                        ['description' => 'Missing name'],
                    ],
                ],
                'shouldPass' => false,
            ],
            'fails if a category name is not a string' => [
                'data' => [
                    'categories' => [
                        ['name' => 123],
                    ],
                ],
                'shouldPass' => false,
            ],
            'fails if description is not a string' => [
                'data' => [
                    'categories' => [
                        ['name' => 'Valid Name', 'description' => 123],
                    ],
                ],
                'shouldPass' => false,
            ],
            'fails if is_active is not a boolean' => [
                'data' => [
                    'categories' => [
                        ['name' => 'Valid Name', 'is_active' => 'not-a-boolean'],
                    ],
                ],
                'shouldPass' => false,
            ],
            'passes with nullable description and is_active' => [
                'data' => [
                    'categories' => [
                        ['name' => 'Just a name'],
                    ],
                ],
                'shouldPass' => true,
            ],
        ];
    }
}
