<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\ServiceCatalog;
use Database\Seeders\ServiceCatalogSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('keeps all php catalog groups and leaf keys in parity', function () {
    $english = catalogs('en');
    $spanish = catalogs('es');

    expect(array_keys($spanish))->toBe(array_keys($english));

    foreach ($english as $group => $catalog) {
        expect($spanish)->toHaveKey($group);
        expect(leafKeys($spanish[$group]))->toBe(leafKeys($catalog));
    }
});
/**
 * @return array<string, array<string, mixed>>
 */
function catalogs(string $locale): array
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
function leafKeys(array $value, string $prefix = ''): array
{
    $keys = [];

    foreach ($value as $key => $child) {
        $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;

        if (is_array($child)) {
            $keys = [...$keys, ...leafKeys($child, $path)];
        } else {
            $keys[] = $path;
        }
    }

    sort($keys);

    return $keys;
}

it('covers domain and custom validation translation keys in both locales', function () {
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

    foreach (literalTranslationKeys(app_path('Http/Requests')) as $key) {
        if (str_starts_with($key, 'validation.') && !str_starts_with($key, 'validation.attributes.')) {
            $keys[] = $key;
        }
    }

    foreach (array_keys(catalogs('en')['notifications']['audit']['subjects']) as $event) {
        $keys[] = "notifications.audit.subjects.{$event}";
        $keys[] = "notifications.audit.events.{$event}";
    }

    foreach (['es', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach (array_unique($keys) as $key) {
            $this->assertNotSame($key, __($key), "Missing {$locale} translation: {$key}");
        }

        $attributes = catalogs($locale)['validation']['attributes'];

        foreach (leafKeys($attributes) as $attribute) {
            $translated = array_key_exists($attribute, $attributes)
                ? $attributes[$attribute]
                : __("validation.attributes.{$attribute}");

            expect($translated)->toBeString();
            $this->assertNotSame("validation.attributes.{$attribute}", $translated);
        }
    }
});
/**
 * @return list<string>
 */
function literalTranslationKeys(string $directory): array
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
