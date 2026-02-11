<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderItemType;
use App\Features\MotorItems;
use App\Http\Requests\CatalogRequest;
use App\Models\ServiceCatalog;
use App\Traits\CachesServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

final class CatalogController extends Controller
{
    use CachesServiceCatalog;

    public function index(CatalogRequest $request): JsonResponse
    {
        // Feature flag check
        if (! Feature::active(MotorItems::class)) {
            abort(404);
        }

        // Prefer validated inputs from FormRequest, fallback to headers/config
        $validated = $request->validated();
        $locale = $validated['locale'] ?? $request->header('Accept-Language') ?? config('app.locale', 'en');
        App::setLocale($locale);

        $itemTypeParam = $validated['item_type'] ?? null;
        if ($itemTypeParam) {
            $type = OrderItemType::tryFrom($itemTypeParam);
            if (! $type) {
                return response()->json(['message' => 'Invalid item_type'], 422);
            }

            $key = self::engineOptionsKey($locale, $type);
            $hit = Cache::has($key);
            $payload = Cache::remember($key, now()->addSeconds(self::ttlEngineOptions()), function () use ($type) {
                return [
                    'item_type' => $type->value,
                    'item_type_label' => $this->itemTypeLabel($type),
                    'components' => $this->mapComponentsForItemType($type),
                    'services' => $this->mapServicesForItemType($type),
                ];
            });

            return response()->json($payload)->header('X-Cache', $hit ? 'HIT' : 'MISS');
        }

        // Full catalog: item types, components_by_type, services_by_type
        $types = OrderItemType::cases();
        $key = self::engineOptionsKey($locale, null);
        $hit = Cache::has($key);
        $payload = Cache::remember($key, now()->addSeconds(self::ttlEngineOptions()), function () use ($types) {
            $itemTypes = array_map(fn ($t) => [
                'key' => $t->value,
                'label' => $this->itemTypeLabel($t),
            ], $types);

            $componentsByType = [];
            $servicesByType = [];

            foreach ($types as $t) {
                $componentsByType[$t->value] = $this->mapComponentsForItemType($t);
                $servicesByType[$t->value] = $this->mapServicesForItemType($t);
            }

            return [
                'item_types' => $itemTypes,
                'components_by_type' => $componentsByType,
                'services_by_type' => $servicesByType,
            ];
        });

        return response()->json($payload)->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * @return array<int, array{service_key: string, service_name: string, base_price: string, net_price: string, requires_measurement: bool, display_order: int, item_type: string}>
     */
    private function mapServicesForItemType(OrderItemType $type): array
    {
        return ServiceCatalog::query()
            ->active()
            ->forItemType($type)
            ->orderBy('display_order')
            ->get()
            ->map(fn (ServiceCatalog $s) => [
                'service_key' => $s->service_key,
                'service_name' => $s->service_name,
                'base_price' => number_format((float) $s->base_price, 2, '.', ''),
                'net_price' => number_format((float) $s->net_price, 2, '.', ''),
                'requires_measurement' => (bool) $s->requires_measurement,
                'display_order' => (int) $s->display_order,
                'item_type' => $s->item_type->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function mapComponentsForItemType(OrderItemType $type): array
    {
        return array_map(fn ($key) => [
            'key' => $key,
            'label' => $this->componentLabel($type, $key),
        ], $type->getComponents());
    }

    private function itemTypeLabel(OrderItemType $type): string
    {
        $key = "motor.item_types.{$type->value}";
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $type->label();
    }

    private function componentLabel(OrderItemType $type, string $componentKey): string
    {
        $key = "motor.components.{$type->value}.{$componentKey}";
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : ucfirst(str_replace('_', ' ', $componentKey));
    }
}
