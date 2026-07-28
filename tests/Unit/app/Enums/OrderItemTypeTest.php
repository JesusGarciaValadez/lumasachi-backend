<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it checks if all values and labels are defined', function (): void {
    $values = array_map(fn($c) => $c->value, OrderItemType::cases());
    expect($values)->toBe([
        'cylinder_head',
        'engine_block',
        'crankshaft',
        'connecting_rods',
        'others',
    ]);

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        foreach (OrderItemType::cases() as $type) {
            $this->assertNotSame("motor.item_types.{$type->value}", $type->label());
        }
    }
});

test('it checks if each type has components', function (): void {
    foreach (OrderItemType::cases() as $type) {
        $components = $type->getComponents();
        expect($components)->toBeArray();
        expect($components)->not->toBeEmpty();
    }
});

test('it has localized labels for every component', function (): void {
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        foreach (OrderItemType::cases() as $type) {
            foreach ($type->getComponents() as $component) {
                $this->assertNotSame(
                    "motor.components.{$type->value}.{$component}",
                    $type->componentLabel($component),
                );
            }
        }
    }
});

test('it uses a visible fallback for an unknown component key', function (): void {
    app()->setLocale('es');

    expect(OrderItemType::EngineBlock->componentLabel('legacy_component'))->toBe('No disponible');
});
