<?php

declare(strict_types=1);

namespace Tests\Unit\app\Enums;

use App\Enums\OrderItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderItemTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_if_all_values_and_labels_are_defined(): void
    {
        $values = array_map(fn ($c) => $c->value, OrderItemType::cases());
        $this->assertSame([
            'cylinder_head',
            'engine_block',
            'crankshaft',
            'connecting_rods',
            'others',
        ], $values);

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);

            foreach (OrderItemType::cases() as $type) {
                $this->assertNotSame("motor.item_types.{$type->value}", $type->label());
            }
        }
    }

    #[Test]
    public function it_checks_if_each_type_has_components(): void
    {
        foreach (OrderItemType::cases() as $type) {
            $components = $type->getComponents();
            $this->assertIsArray($components);
            $this->assertNotEmpty($components);
        }
    }

    #[Test]
    public function it_has_localized_labels_for_every_component(): void
    {
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
    }

    #[Test]
    public function it_uses_a_visible_fallback_for_an_unknown_component_key(): void
    {
        app()->setLocale('es');

        $this->assertSame('No disponible', OrderItemType::EngineBlock->componentLabel('legacy_component'));
    }
}
