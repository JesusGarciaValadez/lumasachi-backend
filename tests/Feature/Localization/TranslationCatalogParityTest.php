<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\ServiceCatalog;
use Database\Seeders\ServiceCatalogSeeder;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

final class TranslationCatalogParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_keeps_all_php_catalog_groups_and_leaf_keys_in_parity(): void
    {
        $english = $this->catalogs('en');
        $spanish = $this->catalogs('es');

        $this->assertSame(array_keys($english), array_keys($spanish));

        foreach ($english as $group => $catalog) {
            $this->assertArrayHasKey($group, $spanish);
            $this->assertSame($this->leafKeys($catalog), $this->leafKeys($spanish[$group]));
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogs(string $locale): array
    {
        $catalogs = [];

        foreach (glob(resource_path("lang/{$locale}/*.php")) ?: [] as $path) {
            $catalogs[pathinfo($path, PATHINFO_FILENAME)] = require $path;
        }

        return $catalogs;
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function leafKeys(array $value, string $prefix = ''): array
    {
        $keys = [];

        foreach ($value as $key => $child) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;

            if (is_array($child)) {
                $keys = [...$keys, ...$this->leafKeys($child, $path)];
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }

    #[Test]
    public function it_covers_domain_and_custom_validation_translation_keys_in_both_locales(): void
    {
        $this->seed(ServiceCatalogSeeder::class);

        $keys = [];

        foreach (OrderStatus::cases() as $status) {
            $keys[] = 'orders.status_labels.' . $status->value;
        }

        foreach (OrderPriority::cases() as $priority) {
            $keys[] = 'orders.priority_labels.' . $priority->value;
        }

        foreach (OrderItemType::cases() as $itemType) {
            $keys[] = 'motor.item_types.' . $itemType->value;

            foreach ($itemType->getComponents() as $component) {
                $keys[] = "motor.components.{$itemType->value}.{$component}";
            }
        }

        foreach (ServiceCatalog::active()->pluck('service_name_key')->unique() as $serviceKey) {
            $keys[] = $serviceKey;
        }

        foreach ($this->literalTranslationKeys(app_path('Http/Requests')) as $key) {
            if (str_starts_with($key, 'validation.') && !str_starts_with($key, 'validation.attributes.')) {
                $keys[] = $key;
            }
        }

        foreach (array_keys($this->catalogs('en')['notifications']['audit']['subjects']) as $event) {
            $keys[] = "notifications.audit.subjects.{$event}";
            $keys[] = "notifications.audit.events.{$event}";
        }

        foreach (['es', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (array_unique($keys) as $key) {
                $this->assertNotSame($key, __($key), "Missing {$locale} translation: {$key}");
            }

            $attributes = $this->catalogs($locale)['validation']['attributes'];

            foreach ($this->leafKeys($attributes) as $attribute) {
                $translated = array_key_exists($attribute, $attributes)
                    ? $attributes[$attribute]
                    : __("validation.attributes.{$attribute}");

                $this->assertIsString($translated);
                $this->assertNotSame("validation.attributes.{$attribute}", $translated);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function literalTranslationKeys(string $directory): array
    {
        $keys = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if ($source === false) {
                continue;
            }

            preg_match_all("/__\\(\\s*['\"]([^'\"]+)['\"]/", $source, $matches);
            $keys = [...$keys, ...($matches[1] ?? [])];
        }

        return array_values(array_unique($keys));
    }
}
