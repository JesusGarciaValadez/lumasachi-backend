<?php

namespace App\Http\Controllers;

use App\Enums\OrderItemType;
use App\Enums\UserRole;
use App\Features\MotorItems;
use App\Models\ServiceCatalog;
use App\Traits\CachesServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

final class CatalogController extends Controller
{
    use CachesServiceCatalog;

    public function index(Request $request): JsonResponse
    {
        // Feature flag check
        if (! Feature::active(MotorItems::class)) {
            abort(404);
        }

        // Authz: Employees/Admins/SuperAdmins only
        $user = $request->user();
        if (! $user || $user->role === UserRole::CUSTOMER) {
            abort(403);
        }

        // Locale from header
        $locale = $request->header('Accept-Language', config('app.locale', 'en'));
        App::setLocale($locale);

        $itemTypeParam = $request->query('item_type');
        if ($itemTypeParam) {
            // Validate item type
            $type = collect(OrderItemType::cases())
                ->first(fn ($c) => $c->value === $itemTypeParam);
            if (! $type) {
                return response()->json(['message' => 'Invalid item_type'], 422);
            }

            $key = self::engineOptionsKey($locale, $type);
            $hit = Cache::has($key);
            $payload = Cache::remember($key, now()->addSeconds(self::ttlEngineOptions()), function () use ($type) {
                $components = array_map(fn ($key) => [
                    'key' => $key,
                    'label' => $this->componentLabel($type, $key),
                ], $type->getComponents());

                $services = ServiceCatalog::query()
                    ->active()
                    ->forItemType($type)
                    ->orderBy('display_order')
                    ->get()
                    ->map(fn (ServiceCatalog $s) => [
                        'service_key' => $s->service_key,
                        'service_name' => $s->service_name,
                        'base_price' => number_format((float)$s->base_price, 2, '.', ''),
                        'net_price' => number_format((float)$s->net_price, 2, '.', ''),
                        'requires_measurement' => (bool) $s->requires_measurement,
                        'display_order' => (int) $s->display_order,
                        'item_type' => $s->item_type->value,
                    ])
                    ->values()
                    ->all();

                return [
                    'item_type' => $type->value,
                    'item_type_label' => $this->itemTypeLabel($type),
                    'components' => $components,
                    'services' => $services,
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
                $componentsByType[$t->value] = array_map(fn ($key) => [
                    'key' => $key,
                    'label' => $this->componentLabel($t, $key),
                ], $t->getComponents());

                $servicesByType[$t->value] = ServiceCatalog::query()
                    ->active()
                    ->forItemType($t)
                    ->orderBy('display_order')
                    ->get()
                    ->map(fn (ServiceCatalog $s) => [
                        'service_key' => $s->service_key,
                        'service_name' => $s->service_name,
                        'base_price' => number_format((float)$s->base_price, 2, '.', ''),
                        'net_price' => number_format((float)$s->net_price, 2, '.', ''),
                        'requires_measurement' => (bool) $s->requires_measurement,
                        'display_order' => (int) $s->display_order,
                        'item_type' => $s->item_type->value,
                    ])->all();
            }

            return [
                'item_types' => $itemTypes,
                'components_by_type' => $componentsByType,
                'services_by_type' => $servicesByType,
            ];
        });

        return response()->json($payload)->header('X-Cache', $hit ? 'HIT' : 'MISS');
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
