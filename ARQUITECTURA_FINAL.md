# 🏗️ Análisis Arquitectónico Final: Sistema de Ítems de Motor

> **Versión Final con Inglés + i18n + UUIDs + Campos de Liquidación**

## 📋 Resumen Ejecutivo

Análisis completo para implementar gestión de ítems de motor en órdenes de trabajo con:
- ✅ Nomenclatura en inglés
- ✅ Soporte completo i18n (en/es)
- ✅ UUIDs en todas las tablas nuevas
- ✅ Campos de costo total y liquidación
- ✅ Comentarios en código en inglés

---

## 🗃️ SOLUCIÓN RECOMENDADA: Modelo Normalizado

### Estructura de Base de Datos

#### 1. order_motor_info

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_motor_info', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            
            // Engine specifications
            $table->string('brand')->nullable();
            $table->string('liters')->nullable();
            $table->string('year')->nullable();
            $table->string('model')->nullable();
            $table->string('cylinder_count')->nullable();
            
            // Financial information
            $table->decimal('down_payment', 10, 2)->nullable()->default(0);
            $table->decimal('total_cost', 10, 2)->nullable()->default(0);
            $table->boolean('is_fully_paid')->default(false);
            
            // Torque specifications
            $table->string('center_torque')->nullable();
            $table->string('rod_torque')->nullable();
            
            // Ring gap measurements
            $table->string('first_gap')->nullable();
            $table->string('second_gap')->nullable();
            $table->string('third_gap')->nullable();
            
            // Lubrication clearances
            $table->string('center_clearance')->nullable();
            $table->string('rod_clearance')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique('order_id');
            $table->index('is_fully_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_motor_info');
    }
};
```

#### 2. order_items

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->enum('item_type', [
                'cylinder_head',
                'engine_block',
                'crankshaft',
                'connecting_rods',
                'others'
            ]);
            $table->boolean('is_received')->default(false);
            $table->timestamps();
            
            // Indexes
            $table->index(['order_id', 'item_type']);
            $table->unique(['order_id', 'item_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
```

#### 3. order_item_components

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_item_components', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            // Component name (e.g., 'camshaft_covers', 'bolts', 'bearing_caps')
            $table->string('component_name');
            $table->boolean('is_received')->default(false);
            $table->timestamps();
            
            // Indexes
            $table->index('order_item_id');
            $table->unique(['order_item_id', 'component_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_components');
    }
};
```

#### 4. order_services

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_services', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            
            // Service reference key (e.g., 'wash_block', 'grind_cylinder')
            $table->string('service_key');
            
            // Optional measurement (e.g., '20' for cylinder size)
            $table->string('measurement')->nullable();
            
            // Service states
            $table->boolean('is_budgeted')->default(false);
            $table->boolean('is_authorized')->default(false);
            $table->boolean('is_completed')->default(false);
            
            // Additional notes
            $table->text('notes')->nullable();
            
            // Pricing (always from catalog)
            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('net_price', 10, 2)->nullable();
            
            $table->timestamps();
            
            // Indexes for filtering by state
            $table->index(['order_item_id', 'is_budgeted', 'is_authorized', 'is_completed']);
            $table->index('service_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_services');
    }
};
```

#### 5. service_catalog

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_catalog', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            
            // Unique service identifier
            $table->string('service_key')->unique();
            
            // Translation key for service name
            $table->string('service_name_key');
            
            // Item type this service applies to
            $table->enum('item_type', [
                'cylinder_head',
                'engine_block',
                'crankshaft',
                'connecting_rods',
                'others'
            ]);
            
            // Pricing
            $table->decimal('base_price', 10, 2);
            $table->decimal('tax_percentage', 5, 2)->default(16.00);
            
            // Service metadata
            $table->boolean('requires_measurement')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['item_type', 'is_active']);
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_catalog');
    }
};
```

---

## 📦 Modelos Eloquent

### OrderMotorInfo

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order Motor Information Model
 * 
 * Stores engine specifications, financial data, and technical measurements
 * for work orders.
 */
final class OrderMotorInfo extends Model
{
    use HasFactory, HasUuids;
    
    protected $table = 'order_motor_info';
    
    protected $fillable = [
        'order_id',
        'brand',
        'liters',
        'year',
        'model',
        'cylinder_count',
        'down_payment',
        'total_cost',
        'is_fully_paid',
        'center_torque',
        'rod_torque',
        'first_gap',
        'second_gap',
        'third_gap',
        'center_clearance',
        'rod_clearance',
    ];
    
    protected function casts(): array
    {
        return [
            'down_payment' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'is_fully_paid' => 'boolean',
        ];
    }
    
    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
    
    /**
     * Get the order that owns this motor info.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    /**
     * Get the remaining balance to be paid.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, $this->total_cost - $this->down_payment);
    }
    
    /**
     * Check if the order has any pending payment.
     */
    public function hasPendingPayment(): bool
    {
        return !$this->is_fully_paid && $this->remaining_balance > 0;
    }
}
```

### OrderItem

```php
<?php

namespace App\Models;

use App\Enums\OrderItemType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order Item Model
 * 
 * Represents a main engine component being serviced
 * (e.g., Cylinder Head, Engine Block, Crankshaft).
 */
final class OrderItem extends Model
{
    use HasFactory, HasUuids;
    
    protected $fillable = [
        'order_id',
        'item_type',
        'is_received',
    ];
    
    protected function casts(): array
    {
        return [
            'is_received' => 'boolean',
            'item_type' => OrderItemType::class,
        ];
    }
    
    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
    
    /**
     * Get the order that owns this item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    /**
     * Get the components for this item.
     */
    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class);
    }
    
    /**
     * Get the services for this item.
     */
    public function services(): HasMany
    {
        return $this->hasMany(OrderService::class);
    }
}
```

### OrderItemComponent

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order Item Component Model
 * 
 * Represents individual parts of a main engine component
 * (e.g., bolts, bearings, valves).
 */
final class OrderItemComponent extends Model
{
    use HasFactory, HasUuids;
    
    protected $fillable = [
        'order_item_id',
        'component_name',
        'is_received',
    ];
    
    protected function casts(): array
    {
        return [
            'is_received' => 'boolean',
        ];
    }
    
    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
    
    /**
     * Get the order item that owns this component.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
    
    /**
     * Get the translated component name.
     */
    public function getComponentLabelAttribute(): string
    {
        $itemType = $this->orderItem->item_type->value;
        return __("motor.components.{$itemType}.{$this->component_name}");
    }
}
```

### OrderService

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order Service Model
 * 
 * Represents a service to be performed on an order item.
 * Tracks budgeted, authorized, and completed states.
 */
final class OrderService extends Model
{
    use HasFactory, HasUuids;
    
    protected $fillable = [
        'order_item_id',
        'service_key',
        'measurement',
        'is_budgeted',
        'is_authorized',
        'is_completed',
        'notes',
        'base_price',
        'net_price',
    ];
    
    protected function casts(): array
    {
        return [
            'is_budgeted' => 'boolean',
            'is_authorized' => 'boolean',
            'is_completed' => 'boolean',
            'base_price' => 'decimal:2',
            'net_price' => 'decimal:2',
        ];
    }
    
    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
    
    /**
     * Get the order item that owns this service.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
    
    /**
     * Get the catalog item for this service.
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_key', 'service_key');
    }
    
    /**
     * Get the translated service name.
     */
    public function getServiceNameAttribute(): string
    {
        return __("services.{$this->service_key}");
    }
}
```

### ServiceCatalog

```php
<?php

namespace App\Models;

use App\Enums\OrderItemType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Service Catalog Model
 * 
 * Master catalog of available services with pricing.
 * Serves as the single source of truth for service prices.
 */
final class ServiceCatalog extends Model
{
    use HasFactory, HasUuids;
    
    protected $table = 'service_catalog';
    
    protected $fillable = [
        'service_key',
        'service_name_key',
        'item_type',
        'base_price',
        'tax_percentage',
        'requires_measurement',
        'is_active',
        'display_order',
    ];
    
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'requires_measurement' => 'boolean',
            'is_active' => 'boolean',
            'item_type' => OrderItemType::class,
        ];
    }
    
    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
    
    /**
     * Get the translated service name.
     */
    public function getServiceNameAttribute(): string
    {
        return __($this->service_name_key);
    }
    
    /**
     * Calculate the net price including tax.
     */
    public function getNetPriceAttribute(): float
    {
        return round($this->base_price * (1 + ($this->tax_percentage / 100)), 2);
    }
    
    /**
     * Scope to only active services.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope to services for a specific item type.
     */
    public function scopeForItemType($query, OrderItemType $itemType)
    {
        return $query->where('item_type', $itemType);
    }
}
```

### Actualizar Order Model

```php
<?php

namespace App\Models;

// ... existing imports ...

final class Order extends Model
{
    // ... existing code ...
    
    /**
     * Get the motor information for this order.
     */
    public function motorInfo(): HasOne
    {
        return $this->hasOne(OrderMotorInfo::class);
    }
    
    /**
     * Get the items for this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    /**
     * Get all services through items.
     */
    public function services(): HasManyThrough
    {
        return $this->hasManyThrough(OrderService::class, OrderItem::class);
    }
    
    /**
     * Get the total budgeted amount.
     */
    public function getTotalBudgetedAttribute(): float
    {
        return $this->services()
            ->where('is_budgeted', true)
            ->sum('net_price') ?? 0;
    }
    
    /**
     * Get the total authorized amount.
     */
    public function getTotalAuthorizedAttribute(): float
    {
        return $this->services()
            ->where('is_authorized', true)
            ->sum('net_price') ?? 0;
    }
    
    /**
     * Get the total completed amount (actual cost).
     */
    public function getTotalCompletedAttribute(): float
    {
        return $this->services()
            ->where('is_completed', true)
            ->sum('net_price') ?? 0;
    }
    
    /**
     * Get the remaining balance to be paid.
     */
    public function getRemainingBalanceAttribute(): float
    {
        $totalCompleted = $this->total_completed;
        $downPayment = $this->motorInfo?->down_payment ?? 0;
        return max(0, $totalCompleted - $downPayment);
    }
    
    /**
     * Check if the order is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->motorInfo?->is_fully_paid ?? false;
    }
    
    /**
     * Update the total cost and payment status.
     */
    public function updateTotalCost(): void
    {
        if ($this->motorInfo) {
            $this->motorInfo->update([
                'total_cost' => $this->total_completed,
                'is_fully_paid' => $this->remaining_balance <= 0,
            ]);
        }
    }
}
```

---

## 🎨 Enums

### OrderItemType

```php
<?php

namespace App\Enums;

enum OrderItemType: string
{
    case CYLINDER_HEAD = 'cylinder_head';
    case ENGINE_BLOCK = 'engine_block';
    case CRANKSHAFT = 'crankshaft';
    case CONNECTING_RODS = 'connecting_rods';
    case OTHERS = 'others';
    
    /**
     * Get the translated label for this item type.
     */
    public function label(): string
    {
        return __("motor.item_types.{$this->value}");
    }
    
    /**
     * Get all possible enum values.
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    /**
     * Get the available components for this item type.
     * 
     * @return array<string> Component keys
     */
    public function getComponents(): array
    {
        return match($this) {
            self::CYLINDER_HEAD => [
                'camshaft_covers',
                'bolts',
                'rocker_arm_shaft',
                'wedges',
                'springs',
                'shims',
                'valves',
                'guides',
            ],
            self::ENGINE_BLOCK => [
                'bearing_caps',
                'cap_bolts',
                'camshaft',
                'guides',
                'bearings',
                'camshaft_key',
                'camshaft_gear',
            ],
            self::CRANKSHAFT => [
                'iron_gear',
                'bronze_gear',
                'lock',
                'key',
                'flywheel',
                'bolt',
                'deflector',
            ],
            self::CONNECTING_RODS => [
                'bolts',
                'nuts',
                'pistons',
                'locks',
                'bearings',
            ],
            self::OTHERS => [
                'water_pump',
                'oil_pump',
                'oil_pan',
                'windage_tray',
                'intake_manifold',
                'exhaust_manifold',
                'timing_covers',
            ],
        };
    }
    
    /**
     * Get the translated component label.
     */
    public function getComponentLabel(string $componentKey): string
    {
        return __("motor.components.{$this->value}.{$componentKey}");
    }
}
```

### OrderStatus (Updated)

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    // New workflow states
    case RECEIVED = 'received';
    case AWAITING_REVIEW = 'awaiting_review';
    case REVIEWED = 'reviewed';
    case AWAITING_CUSTOMER_APPROVAL = 'awaiting_customer_approval';
    case READY_FOR_WORK = 'ready_for_work';
    
    // Existing states
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case PAID = 'paid';
    case RETURNED = 'returned';
    case NOT_PAID = 'not_paid';
    case ON_HOLD = 'on_hold';
    case CANCELLED = 'cancelled';
    
    /**
     * Get all possible status values.
     */
    public static function getStatuses(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    /**
     * Get the translated label for this status.
     */
    public function label(): string
    {
        return __("orders.status.{$this->value}");
    }
    
    /**
     * Check if customer should be notified for this status.
     */
    public function shouldNotifyCustomer(): bool
    {
        return in_array($this, [
            self::RECEIVED,
            self::REVIEWED,
            self::READY_FOR_DELIVERY,
            self::DELIVERED,
        ]);
    }
    
    /**
     * Check if admins should be notified for this status.
     */
    public function shouldNotifyAdmins(): bool
    {
        return in_array($this, [
            self::RECEIVED,
            self::REVIEWED,
            self::DELIVERED,
        ]);
    }
}
```

---

## 🌐 Archivos de Traducción

Ver **ARQUITECTURA_CORRECCIONES_I18N.md** para los archivos completos de traducción en:
- `resources/lang/en/motor.php`
- `resources/lang/es/motor.php`
- `resources/lang/en/services.php`
- `resources/lang/es/services.php`
- `resources/lang/en/orders.php`
- `resources/lang/es/orders.php`

---

## 📊 Cambios Clave de la Versión Final

### 1. UUIDs Agregados ✅
Todas las nuevas tablas incluyen:
```php
$table->uuid()->unique();

// En el modelo:
use HasUuids;

public function uniqueIds(): array
{
    return ['uuid'];
}
```

### 2. Campos de Liquidación ✅
```php
// En order_motor_info:
$table->decimal('total_cost', 10, 2)->nullable()->default(0);
$table->boolean('is_fully_paid')->default(false);

// Accessors en Order model:
public function getRemainingBalanceAttribute(): float
public function isFullyPaid(): bool
public function updateTotalCost(): void
```

### 3. Comentarios en Inglés ✅
```php
/**
 * Get the order that owns this motor info.
 */
public function order(): BelongsTo

// Comments explaining logic
// Update total cost when services are completed
```

### 4. Soporte i18n Completo ✅
```php
/**
 * Get the translated service name.
 */
public function getServiceNameAttribute(): string
{
    return __("services.{$this->service_key}");
}
```

---

## 📋 Plan de Implementación (20-22 días)

### Fase 1: Base de Datos (2-3 días)
- [ ] Crear 5 migraciones con UUIDs
- [ ] Actualizar OrderStatus enum
- [ ] Crear OrderItemType enum
- [ ] Seeders para service_catalog

### Fase 2: Modelos (2-3 días)
- [ ] Crear 5 modelos con HasUuids
- [ ] Agregar relaciones en Order
- [ ] Crear Observers
- [ ] Factories con UUIDs

### Fase 3: Controllers y API (3-4 días)
- [ ] OrderMotorInfoController
- [ ] OrderItemController
- [ ] OrderServiceController
- [ ] Endpoints con locale support
- [ ] API Resources bilingües

### Fase 4: Business Logic (2-3 días)
- [ ] Cálculo de totales
- [ ] Lógica de liquidación
- [ ] Auto-actualización de total_cost
- [ ] Transiciones de estado

### Fase 5: Notificaciones (2 días)
- [ ] 4 mails para cliente
- [ ] Mail de auditoría
- [ ] Actualizar OrderObserver

### Fase 6: Testing (5-6 días)
- [ ] 20+ unit tests
- [ ] 25+ feature tests
- [ ] Tests de liquidación
- [ ] Tests bilingües

### Fase 7: Frontend (4-5 días)
- [ ] Componentes Vue con i18n
- [ ] Selector de idioma
- [ ] Formularios traducidos

---

## ✅ Checklist Final

### Base de Datos
- [x] Nombres de columnas en inglés
- [x] Enum values en inglés
- [x] UUIDs en todas las tablas nuevas
- [x] Campo `total_cost` agregado
- [x] Campo `is_fully_paid` agregado

### Código
- [x] Comentarios en inglés
- [x] Variables en inglés
- [x] Métodos en inglés
- [x] Accessors para i18n

### i18n
- [x] Archivos en/es creados
- [x] Translation keys definidos
- [x] Accessors automáticos

### Business Logic
- [x] Cálculo de totales
- [x] Cálculo de balance restante
- [x] Check de liquidación completa

---

## 📚 Documentos Relacionados

1. **ARQUITECTURA_CORRECCIONES_I18N.md** - Archivos de traducción completos
2. **ARQUITECTURA_DIAGRAMAS.md** - Diagramas visuales
3. **ARQUITECTURA_RESUMEN_EJECUTIVO.md** - Resumen para stakeholders
4. **LEEME_PRIMERO_V2.md** - Guía de inicio

---

**Versión**: Final (con UUIDs + Liquidación + i18n)  
**Proyecto**: Lumasachi Backend  
**Fecha**: 2025  
**Estado**: ✅ Listo para Implementación
