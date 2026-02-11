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

        $this->assertSame('Cylinder Head', OrderItemType::CylinderHead->label());
        $this->assertSame('Engine Block', OrderItemType::EngineBlock->label());
        $this->assertSame('Crankshaft', OrderItemType::Crankshaft->label());
        $this->assertSame('Connecting Rods', OrderItemType::ConnectingRods->label());
        $this->assertSame('Others', OrderItemType::Others->label());
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
}
