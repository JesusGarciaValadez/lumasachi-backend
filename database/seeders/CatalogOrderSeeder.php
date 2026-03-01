<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderMotorInfo;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds realistic work orders with motor info, items, components, and services
 * based on the physical work order forms (work_order_general.jpeg, work_order_cabeza.jpeg)
 * and the official price list (price_list_1.jpeg, price_list_2.jpeg).
 */
final class CatalogOrderSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', UserRole::ADMINISTRATOR->value)->first();
        $employee = User::where('role', UserRole::EMPLOYEE->value)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER->value)->where('is_active', true)->first();

        if (! $admin || ! $employee || ! $customer) {
            $this->command->warn('CatalogOrderSeeder requires existing admin, employee and customer users. Skipping.');

            return;
        }

        $this->seedOrderFordFiesta($employee, $customer);
        $this->seedOrderVWBeetle($admin, $employee, $customer);
        $this->seedOrderNissanAltima($admin, $employee, $customer);

        $this->command->info('CatalogOrderSeeder: 3 realistic work orders created with motor info, items, components and services.');
    }

    /**
     * Order 1 — Ford Fiesta 1.6 4cyl (based on work_order_general.jpeg)
     * Block + Crankshaft + Connecting Rods services, with measurements.
     */
    private function seedOrderFordFiesta(User $employee, User $customer): void
    {
        $order = Order::factory()->createQuietly([
            'uuid' => Str::uuid7()->toString(),
            'customer_id' => $customer->id,
            'title' => 'Rectificado Ford Fiesta 1.6 – Block, Cigüeñal y Bielas',
            'description' => 'Orden de trabajo #1140. Lavado, rectificado de cilindros, ajuste de bancada, cepillado, rectificado de cigüeñal y bielas completo.',
            'status' => OrderStatus::InProgress->value,
            'priority' => OrderPriority::HIGH->value,
            'estimated_completion' => Carbon::now()->addDays(10),
            'created_by' => $customer->id,
            'updated_by' => $employee->id,
            'assigned_to' => $employee->id,
            'notes' => 'Automotor. Torque centro: 74, Torque biela: 30+90°. Luz centro: 0.0006/0.003, Luz biela: 0.0009/0.0046',
        ]);

        OrderMotorInfo::factory()->withMeasurements()->create([
            'order_id' => $order->id,
            'brand' => 'Ford',
            'model' => 'Fiesta',
            'liters' => '1.6',
            'year' => '2018',
            'cylinder_count' => 4,
            'down_payment' => 0,
            'total_cost' => 0,
            'is_fully_paid' => false,
            'center_torque' => '74',
            'rod_torque' => '30+90',
            'center_clearance' => '0.0006/0.003',
            'rod_clearance' => '0.0009/0.0046',
        ]);

        // ── Block item with components and services ──
        $blockItem = $order->items()->create([
            'item_type' => OrderItemType::EngineBlock->value,
            'is_received' => true,
        ]);

        foreach (['bearing_caps', 'cap_bolts', 'camshaft', 'guides', 'bearings'] as $comp) {
            $blockItem->components()->create([
                'component_name' => $comp,
                'is_received' => true,
            ]);
        }

        $blockServices = ['wash_block', 'bore_cylinder_pu', 'align_bore_main_4cyl', 'deck_block_4cyl', 'replace_cam_bearings', 'polish_camshaft_bars'];
        $this->attachServices($blockItem->id, $blockServices, budgeted: true);

        // ── Crankshaft item with components and services ──
        $crankItem = $order->items()->create([
            'item_type' => OrderItemType::Crankshaft->value,
            'is_received' => true,
        ]);

        foreach (['iron_gear', 'key', 'flywheel', 'bolt'] as $comp) {
            $crankItem->components()->create([
                'component_name' => $comp,
                'is_received' => true,
            ]);
        }

        $crankServices = ['polish_crank_4cyl', 'grind_crank_4cyl', 'fill_thrust', 'fill_journal'];
        $this->attachServices($crankItem->id, $crankServices, budgeted: true, measurement: '20');

        // ── Connecting Rods item with components and services ──
        $rodsItem = $order->items()->create([
            'item_type' => OrderItemType::ConnectingRods->value,
            'is_received' => true,
        ]);

        foreach (['bolts', 'nuts', 'pistons', 'bearings'] as $comp) {
            $rodsItem->components()->create([
                'component_name' => $comp,
                'is_received' => true,
            ]);
        }

        $rodServices = ['resize_rods_4', 'fit_rods_to_crank_4', 'press_pistons_4'];
        $this->attachServices($rodsItem->id, $rodServices, budgeted: true);
    }

    /**
     * Order 2 — VW Beetle 1.6 (based on work_order_cabeza.jpeg / price_list_2.jpeg)
     * Cylinder Head services for a Volkswagen air-cooled engine.
     */
    private function seedOrderVWBeetle(User $admin, User $employee, User $customer): void
    {
        $customer2 = User::where('role', UserRole::CUSTOMER->value)
            ->where('is_active', true)
            ->where('id', '!=', $customer->id)
            ->first() ?? $customer;

        $order = Order::factory()->createQuietly([
            'uuid' => Str::uuid7()->toString(),
            'customer_id' => $customer2->id,
            'title' => 'Rectificado VW Beetle – Cabeza y Especiales',
            'description' => 'Orden de trabajo #3476. Lavado, prueba hidráulica, guías, asientos, soldadura y cepillado de cabeza VW.',
            'status' => OrderStatus::AwaitingCustomerApproval->value,
            'priority' => OrderPriority::NORMAL->value,
            'estimated_completion' => Carbon::now()->addDays(14),
            'created_by' => $customer2->id,
            'updated_by' => $admin->id,
            'assigned_to' => $employee->id,
            'notes' => 'Soldadura por punto: 1 peso. Cambiar árbol. Soldar torno.',
        ]);

        OrderMotorInfo::factory()->create([
            'order_id' => $order->id,
            'brand' => 'Volkswagen',
            'model' => 'Beetle',
            'liters' => '1.6',
            'year' => '2003',
            'cylinder_count' => 4,
            'down_payment' => 1500.00,
            'total_cost' => 0,
            'is_fully_paid' => false,
        ]);

        // ── Others (VW) item with services ──
        $vwItem = $order->items()->create([
            'item_type' => OrderItemType::Others->value,
            'is_received' => true,
        ]);

        foreach (['water_pump', 'oil_pump', 'timing_covers'] as $comp) {
            $vwItem->components()->create([
                'component_name' => $comp,
                'is_received' => true,
            ]);
        }

        $vwServices = [
            'vw_wash_head', 'vw_pressure_test', 'vw_guide_replacement',
            'vw_seat_grind', 'vw_surface_head', 'vw_valve_adjust',
            'vw_bore_cylinders', 'vw_crank_polish',
        ];
        $this->attachServices($vwItem->id, $vwServices, budgeted: true);
    }

    /**
     * Order 3 — Nissan Altima 2.5 4cyl
     * Full rebuild: Head + Block. Completed and paid order.
     */
    private function seedOrderNissanAltima(User $admin, User $employee, User $customer): void
    {
        $order = Order::factory()->createQuietly([
            'uuid' => Str::uuid7()->toString(),
            'customer_id' => $customer->id,
            'title' => 'Reconstrucción Nissan Altima 2.5 – Cabeza y Block',
            'description' => 'Orden completada. Lavado, prueba, cepillado de cabeza, rectificado de cilindros del block, ajuste de bancada.',
            'status' => OrderStatus::Paid->value,
            'priority' => OrderPriority::NORMAL->value,
            'estimated_completion' => Carbon::now()->subDays(5),
            'actual_completion' => Carbon::now()->subDays(3),
            'created_by' => $customer->id,
            'updated_by' => $admin->id,
            'assigned_to' => $employee->id,
        ]);

        OrderMotorInfo::factory()->withMeasurements()->fullyPaid()->create([
            'order_id' => $order->id,
            'brand' => 'Nissan',
            'model' => 'Altima',
            'liters' => '2.5',
            'year' => '2020',
            'cylinder_count' => 4,
            'down_payment' => 5000.00,
            'total_cost' => 4280.00,
            'is_fully_paid' => true,
        ]);

        // ── Cylinder Head item ──
        $headItem = $order->items()->create([
            'item_type' => OrderItemType::CylinderHead->value,
            'is_received' => true,
        ]);

        foreach (['camshaft_covers', 'bolts', 'springs', 'valves', 'guides'] as $comp) {
            $headItem->components()->create([
                'component_name' => $comp,
                'is_received' => true,
            ]);
        }

        $headServices = ['wash_head_4cyl', 'pressure_test_head_4cyl', 'surface_head_4cyl', 'grind_seat_pu', 'valve_grinding'];
        $this->attachServices($headItem->id, $headServices, budgeted: true, authorized: true, completed: true);

        // ── Block item ──
        $blockItem = $order->items()->create([
            'item_type' => OrderItemType::EngineBlock->value,
            'is_received' => true,
        ]);

        foreach (['bearing_caps', 'cap_bolts', 'camshaft', 'bearings'] as $comp) {
            $blockItem->components()->create([
                'component_name' => $comp,
                'is_received' => true,
            ]);
        }

        $blockServices = ['wash_block', 'bore_cylinder_pu', 'align_bore_main_4cyl', 'hone_main_4cyl'];
        $this->attachServices($blockItem->id, $blockServices, budgeted: true, authorized: true, completed: true);
    }

    /**
     * Attach catalog services to an order item by their service_key.
     */
    private function attachServices(
        int $orderItemId,
        array $serviceKeys,
        bool $budgeted = false,
        bool $authorized = false,
        bool $completed = false,
        ?string $measurement = null,
    ): void {
        foreach ($serviceKeys as $key) {
            $catalog = ServiceCatalog::where('service_key', $key)->first();

            if (! $catalog) {
                continue;
            }

            OrderService::create([
                'order_item_id' => $orderItemId,
                'service_key' => $key,
                'measurement' => $catalog->requires_measurement ? ($measurement ?? (string) rand(10, 30)) : null,
                'is_budgeted' => $budgeted,
                'is_authorized' => $authorized,
                'is_completed' => $completed,
                'base_price' => $catalog->base_price,
                'net_price' => $catalog->net_price,
            ]);
        }
    }
}
