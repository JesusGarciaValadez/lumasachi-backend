# 🌍 Correcciones: Nomenclatura en Inglés + Soporte i18n

## 📋 Resumen de Cambios

Se han corregido todos los documentos de arquitectura para:
1. ✅ Nombres de columnas en inglés
2. ✅ Nombres de variables en inglés
3. ✅ Soporte completo para i18n (internacionalización)
4. ✅ Translation keys para todos los textos visibles

---

## 🔄 Mapeo de Cambios: Español → Inglés

### Nombres de Columnas

| Español (❌ Incorrecto) | Inglés (✅ Correcto) |
|-------------------------|----------------------|
| marca | brand |
| litros | liters |
| año | year |
| modelo | model |
| numero_cilindros | cylinder_count |
| monto_anticipo | down_payment |
| torque_centro | center_torque |
| torque_biela | rod_torque |
| gap_primera | first_gap |
| gap_segunda | second_gap |
| gap_tercera | third_gap |
| luz_centro | center_clearance |
| luz_biela | rod_clearance |
| cigueñal | crankshaft |
| bielas | connecting_rods |

### Enum Values

| Español (❌) | Inglés (✅) |
|--------------|-------------|
| 'cabeza' | 'cylinder_head' |
| 'block' | 'engine_block' |
| 'cigueñal' | 'crankshaft' |
| 'bielas' | 'connecting_rods' |
| 'otros' | 'others' |

### Order Status

| Español (❌) | Inglés (✅) | Translation Key |
|--------------|-------------|-----------------|
| 'Recibido' | 'received' | order_status.received |
| 'Esperando Revisión' | 'awaiting_review' | order_status.awaiting_review |
| 'Revisado' | 'reviewed' | order_status.reviewed |
| 'Esperando Aprobación del Cliente' | 'awaiting_customer_approval' | order_status.awaiting_customer_approval |
| 'Listo para Trabajo' | 'ready_for_work' | order_status.ready_for_work |
| 'Listo para Entrega' | 'ready_for_delivery' | order_status.ready_for_delivery |
| 'Entregada' | 'delivered' | order_status.delivered |

---

## 📁 Estructura de i18n

### Archivos de Traducción

```
resources/lang/
├── en/
│   ├── orders.php
│   ├── motor.php
│   ├── services.php
│   └── validation.php
└── es/
    ├── orders.php
    ├── motor.php
    ├── services.php
    └── validation.php
```

---

## 🗃️ Migraciones Corregidas

### 1. order_motor_info (Corregida)

```php
Schema::create('order_motor_info', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')
        ->constrained('orders')
        ->cascadeOnDelete()
        ->cascadeOnUpdate();
    
    // Motor information
    $table->string('brand')->nullable();
    $table->string('liters')->nullable();
    $table->string('year')->nullable();
    $table->string('model')->nullable();
    $table->string('cylinder_count')->nullable();
    
    // Down payment
    $table->decimal('down_payment', 10, 2)->nullable();
    
    // Torques
    $table->string('center_torque')->nullable();
    $table->string('rod_torque')->nullable();
    
    // Ring gaps
    $table->string('first_gap')->nullable();
    $table->string('second_gap')->nullable();
    $table->string('third_gap')->nullable();
    
    // Lubrication clearances
    $table->string('center_clearance')->nullable();
    $table->string('rod_clearance')->nullable();
    
    $table->timestamps();
    
    $table->unique('order_id');
});
```

### 2. order_items (Corregida)

```php
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
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
    
    $table->index(['order_id', 'item_type']);
    $table->unique(['order_id', 'item_type']);
});
```

### 3. service_catalog (Corregida)

```php
Schema::create('service_catalog', function (Blueprint $table) {
    $table->id();
    $table->string('service_key')->unique();
    $table->string('service_name_key'); // Translation key
    $table->enum('item_type', [
        'cylinder_head',
        'engine_block',
        'crankshaft',
        'connecting_rods',
        'others'
    ]);
    $table->decimal('base_price', 10, 2);
    $table->decimal('tax_percentage', 5, 2)->default(16.00);
    $table->boolean('requires_measurement')->default(false);
    $table->boolean('is_active')->default(true);
    $table->integer('display_order')->default(0);
    $table->timestamps();
    
    $table->index(['item_type', 'is_active']);
    $table->index('display_order');
});
```

### 4. order_services (Corregida)

```php
Schema::create('order_services', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_item_id')
        ->constrained('order_items')
        ->cascadeOnDelete()
        ->cascadeOnUpdate();
    $table->string('service_key'); // Reference to service_catalog
    $table->string('measurement')->nullable();
    $table->boolean('is_budgeted')->default(false);
    $table->boolean('is_authorized')->default(false);
    $table->boolean('is_completed')->default(false);
    $table->text('notes')->nullable();
    $table->decimal('base_price', 10, 2)->nullable();
    $table->decimal('net_price', 10, 2)->nullable();
    $table->timestamps();
    
    $table->index(['order_item_id', 'is_budgeted', 'is_authorized', 'is_completed']);
});
```

---

## 📦 Modelos Corregidos

### OrderMotorInfo

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderMotorInfo extends Model
{
    use HasFactory;
    
    protected $table = 'order_motor_info';
    
    protected $fillable = [
        'order_id',
        'brand',
        'liters',
        'year',
        'model',
        'cylinder_count',
        'down_payment',
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
        ];
    }
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
```

### OrderService

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderService extends Model
{
    use HasFactory;
    
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
    
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
    
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_key', 'service_key');
    }
    
    // Accessor for translated service name
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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ServiceCatalog extends Model
{
    use HasFactory;
    
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
    
    // Accessor for translated service name
    public function getServiceNameAttribute(): string
    {
        return __($this->service_name_key);
    }
    
    public function getNetPriceAttribute(): float
    {
        return round($this->base_price * (1 + ($this->tax_percentage / 100)), 2);
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeForItemType($query, OrderItemType $itemType)
    {
        return $query->where('item_type', $itemType);
    }
}
```

---

## 🎨 Enums Corregidos

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
    
    public function label(): string
    {
        return __("motor.item_types.{$this->value}");
    }
    
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
    
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
}
```

### OrderStatus (Actualizado)

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
    
    public static function getStatuses(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    public function label(): string
    {
        return __("orders.status.{$this->value}");
    }
    
    public function shouldNotifyCustomer(): bool
    {
        return in_array($this, [
            self::RECEIVED,
            self::REVIEWED,
            self::READY_FOR_DELIVERY,
            self::DELIVERED,
        ]);
    }
    
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

### resources/lang/en/motor.php

```php
<?php

return [
    'item_types' => [
        'cylinder_head' => 'Cylinder Head',
        'engine_block' => 'Engine Block',
        'crankshaft' => 'Crankshaft',
        'connecting_rods' => 'Connecting Rods',
        'others' => 'Others',
    ],
    
    'components' => [
        'cylinder_head' => [
            'camshaft_covers' => 'Camshaft Covers',
            'bolts' => 'Bolts',
            'rocker_arm_shaft' => 'Rocker Arm Shaft',
            'wedges' => 'Wedges',
            'springs' => 'Springs',
            'shims' => 'Shims',
            'valves' => 'Valves',
            'guides' => 'Guides',
        ],
        'engine_block' => [
            'bearing_caps' => 'Bearing Caps',
            'cap_bolts' => 'Cap Bolts',
            'camshaft' => 'Camshaft',
            'guides' => 'Guides',
            'bearings' => 'Bearings',
            'camshaft_key' => 'Camshaft Key',
            'camshaft_gear' => 'Camshaft Gear',
        ],
        'crankshaft' => [
            'iron_gear' => 'Iron Gear',
            'bronze_gear' => 'Bronze Gear',
            'lock' => 'Lock',
            'key' => 'Key',
            'flywheel' => 'Flywheel',
            'bolt' => 'Bolt',
            'deflector' => 'Deflector',
        ],
        'connecting_rods' => [
            'bolts' => 'Bolts',
            'nuts' => 'Nuts',
            'pistons' => 'Pistons',
            'locks' => 'Locks',
            'bearings' => 'Bearings',
        ],
        'others' => [
            'water_pump' => 'Water Pump',
            'oil_pump' => 'Oil Pump',
            'oil_pan' => 'Oil Pan',
            'windage_tray' => 'Windage Tray',
            'intake_manifold' => 'Intake Manifold',
            'exhaust_manifold' => 'Exhaust Manifold',
            'timing_covers' => 'Timing Covers',
        ],
    ],
    
    'fields' => [
        'brand' => 'Brand',
        'liters' => 'Liters',
        'year' => 'Year',
        'model' => 'Model',
        'cylinder_count' => 'Number of Cylinders',
        'down_payment' => 'Down Payment',
        'center_torque' => 'Center Torque',
        'rod_torque' => 'Rod Torque',
        'first_gap' => 'First Ring Gap',
        'second_gap' => 'Second Ring Gap',
        'third_gap' => 'Third Ring Gap',
        'center_clearance' => 'Center Lubrication Clearance',
        'rod_clearance' => 'Rod Lubrication Clearance',
    ],
];
```

### resources/lang/es/motor.php

```php
<?php

return [
    'item_types' => [
        'cylinder_head' => 'Cabeza',
        'engine_block' => 'Block',
        'crankshaft' => 'Cigüeñal',
        'connecting_rods' => 'Bielas',
        'others' => 'Otros',
    ],
    
    'components' => [
        'cylinder_head' => [
            'camshaft_covers' => 'Tapas de Árbol',
            'bolts' => 'Tornillos',
            'rocker_arm_shaft' => 'Barra de Balancines',
            'wedges' => 'Cuñas',
            'springs' => 'Resortes',
            'shims' => 'Lainas',
            'valves' => 'Válvulas',
            'guides' => 'Guías',
        ],
        'engine_block' => [
            'bearing_caps' => 'Tapas Cojinete',
            'cap_bolts' => 'Tornillos de Tapas',
            'camshaft' => 'Árbol',
            'guides' => 'Guías',
            'bearings' => 'Metales',
            'camshaft_key' => 'Cuña de Árbol',
            'camshaft_gear' => 'Engrane de Árbol',
        ],
        'crankshaft' => [
            'iron_gear' => 'Engrane de Fierro',
            'bronze_gear' => 'Engrane de Bronce',
            'lock' => 'Seguro',
            'key' => 'Cuña',
            'flywheel' => 'Volante',
            'bolt' => 'Tornillo',
            'deflector' => 'Deflector',
        ],
        'connecting_rods' => [
            'bolts' => 'Tornillos',
            'nuts' => 'Tuercas',
            'pistons' => 'Pistones',
            'locks' => 'Seguros',
            'bearings' => 'Metales',
        ],
        'others' => [
            'water_pump' => 'Bomba de Agua',
            'oil_pump' => 'Bomba de Aceite',
            'oil_pan' => 'Carter',
            'windage_tray' => 'Sobrecarte',
            'intake_manifold' => 'Múltiple Admisión',
            'exhaust_manifold' => 'Múltiple Escape',
            'timing_covers' => 'Tapas de Distribución',
        ],
    ],
    
    'fields' => [
        'brand' => 'Marca',
        'liters' => 'Litros',
        'year' => 'Año',
        'model' => 'Modelo',
        'cylinder_count' => 'N° de Cilindros',
        'down_payment' => 'Monto de Anticipo',
        'center_torque' => 'Tórque Centro',
        'rod_torque' => 'Tórque Biela',
        'first_gap' => 'GAP 1ra',
        'second_gap' => 'GAP 2da',
        'third_gap' => 'GAP 3ra',
        'center_clearance' => 'Luz Centro',
        'rod_clearance' => 'Luz Biela',
    ],
];
```

### resources/lang/en/services.php

```php
<?php

return [
    // Cylinder Head Services
    'wash_cylinder_head_4cyl' => 'Wash Cylinder Head 4 Cyl',
    'wash_cylinder_heads_v6' => 'Wash Cylinder Heads V6',
    'wash_cylinder_heads_v8' => 'Wash Cylinder Heads V8',
    'hydraulic_test_4cyl' => 'Hydraulic Test Cylinder Head 4 Cyl',
    'hydraulic_test_v6_v8' => 'Hydraulic Test Cylinder Head V6/V8',
    'plane_cylinder_head_4cyl' => 'Plane Cylinder Head 4 Cylinders',
    'plane_cylinder_heads_v6_v8' => 'Plane Cylinder Heads V6/V8',
    'spot_welding' => 'Spot Welding',
    'guide_kline' => 'Guide K-Line (P.U.)',
    'false_guides' => 'False Guides (P.U.)',
    'seat_grinding' => 'Seat Grinding (P.U.)',
    'seat_machining' => 'Seat Machining (P.U.)',
    'seat_bushing' => 'Seat Bushing (P.U.)',
    'valve_grinding' => 'Valve Grinding (P.U.)',
    'calibrate_16_valves' => 'Calibrate 16 Valves',
    'assemble_16_valves' => 'Assemble 16 Valves',
    'lifter_service' => 'Lifter Service',
    'straighten_cylinder_head' => 'Straighten Cylinder Head',
    'straighten_diesel_cylinder_head' => 'Straighten Diesel Cylinder Head',
    'stud_thread' => 'Stud Thread',
    'spark_plug_threads' => 'Spark Plug Threads',
    'triton_special_thread' => 'Triton Special Thread',
    
    // Engine Block Services (Monoblocks)
    'wash_block' => 'Wash',
    'grind_cylinder' => 'Grind Cylinder (P.U.)',
    'sleeve_cylinder' => 'Sleeve Cylinder (P.U.)',
    'polish_cylinder' => 'Polish Cylinder (P.U.)',
    'align_main_bearing' => 'Align Main Bearing (4 Cylinders)',
    'hone_main_bearing' => 'Hone Main Bearing (4 Cylinders)',
    'plane_4cyl' => 'Plane 4 Cylinders',
    'plane_6cyl' => 'Plane 6 Cylinders',
    'plane_8cyl' => 'Plane 8 Cylinders',
    'plane_assembled_block_4cyl' => 'Plane Assembled Block 4 Cylinders',
    'plane_assembled_block_v6' => 'Plane Assembled Block V6',
    'change_camshaft_bearings' => 'Change Camshaft Bearings',
    'polish_camshaft_or_rocker_arms' => 'Polish Camshaft or Rocker Arms',
    'weld_between_cylinders_qr25' => 'Weld Between Cylinders QR25',
    
    // Crankshaft Services
    'polish_crankshaft_4cyl' => 'Polish Crankshaft (4 Cylinders)',
    'polish_crankshaft_6cyl' => 'Polish Crankshaft (6 Cylinders)',
    'polish_crankshaft_8cyl' => 'Polish Crankshaft (8 Cylinders)',
    'grind_crankshaft_4cyl' => 'Grind Crankshaft (4 Cylinders)',
    'grind_crankshaft_6cyl' => 'Grind Crankshaft (6 Cylinders)',
    'grind_crankshaft_8cyl' => 'Grind Crankshaft (8 Cylinders)',
    'straighten_crankshaft' => 'Straighten Crankshaft',
    'build_up_flange' => 'Build Up Flange',
    'build_up_journal' => 'Build Up Journal',
    'dynamic_balancing_4cyl' => 'Dynamic Balancing 4 Cylinders',
    'dynamic_balancing_6cyl' => 'Dynamic Balancing 6 Cylinders',
    'dynamic_balancing_8cyl' => 'Dynamic Balancing 8 Cylinders',
    
    // Connecting Rods Services
    'grind_connecting_rods' => 'Grind Connecting Rods (4 Rods)',
    'align_rods_to_crankshaft' => 'Align Rods to Crankshaft (4 Rods)',
    'assemble_pistons_press_fit' => 'Assemble Pistons Press Fit (4)',
    'assemble_pistons_with_lock' => 'Assemble Pistons with Lock (4)',
    
    'states' => [
        'is_budgeted' => 'Budgeted',
        'is_authorized' => 'Authorized',
        'is_completed' => 'Completed',
    ],
];
```

### resources/lang/es/services.php

```php
<?php

return [
    // Servicios de Cabeza
    'wash_cylinder_head_4cyl' => 'Lavado Cabeza 4 Cil',
    'wash_cylinder_heads_v6' => 'Lavado Cabezas V6',
    'wash_cylinder_heads_v8' => 'Lavado Cabezas V8',
    'hydraulic_test_4cyl' => 'Prueba Hidráulica Cabeza 4 Cil',
    'hydraulic_test_v6_v8' => 'Prueba Hidráulica Cabeza V6/V8',
    'plane_cylinder_head_4cyl' => 'Cepillado Cabeza 4 Cilindros',
    'plane_cylinder_heads_v6_v8' => 'Cepillado Cabezas V6/V8',
    'spot_welding' => 'Soldadura por Punto',
    'guide_kline' => 'Guía K-Line (P.U.)',
    'false_guides' => 'Guías Postizas (P.U.)',
    'seat_grinding' => 'Rectificado de Asiento (P.U.)',
    'seat_machining' => 'Maquinado de Asiento (P.U.)',
    'seat_bushing' => 'Encasquillado de Asiento (P.U.)',
    'valve_grinding' => 'Rectificado de Válvulas (P.U.)',
    'calibrate_16_valves' => 'Calibrado 16 Válvulas',
    'assemble_16_valves' => 'Armado 16 Válvulas',
    'lifter_service' => 'Servicio a Buzos',
    'straighten_cylinder_head' => 'Enderezado Cabeza',
    'straighten_diesel_cylinder_head' => 'Enderezado Cabeza Diésel',
    'stud_thread' => 'Cuerda de Birlo',
    'spark_plug_threads' => 'Cuerdas de Bujía',
    'triton_special_thread' => 'Cuerda Especial de Tritón',
    
    // Servicios de Block (Monoblocks)
    'wash_block' => 'Lavado',
    'grind_cylinder' => 'Rectificado por Cilindro (P.U.)',
    'sleeve_cylinder' => 'Encamisado por Cilindro (P.U.)',
    'polish_cylinder' => 'Pulido por Cilindro (P.U.)',
    'align_main_bearing' => 'Ajuste de Bancada (4 Cilindros)',
    'hone_main_bearing' => 'Honeado de Bancada (4 Cilindros)',
    'plane_4cyl' => 'Cepillado 4 Cilindros',
    'plane_6cyl' => 'Cepillado 6 Cilindros',
    'plane_8cyl' => 'Cepillado 8 Cilindros',
    'plane_assembled_block_4cyl' => 'Cepillado Block Armado 4 Cilindros',
    'plane_assembled_block_v6' => 'Cepillado Block Armado V6',
    'change_camshaft_bearings' => 'Cambio de Metales de Árbol',
    'polish_camshaft_or_rocker_arms' => 'Pulido de Árbol o Barras Balanceadoras',
    'weld_between_cylinders_qr25' => 'Soldadura Entre Cilindros QR25',
    
    // Servicios de Cigüeñal
    'polish_crankshaft_4cyl' => 'Pulido Cigüeñal (4 Cilindros)',
    'polish_crankshaft_6cyl' => 'Pulido Cigüeñal (6 Cilindros)',
    'polish_crankshaft_8cyl' => 'Pulido Cigüeñal (8 Cilindros)',
    'grind_crankshaft_4cyl' => 'Rectificado de Cigüeñal (4 Cilindros)',
    'grind_crankshaft_6cyl' => 'Rectificado de Cigüeñal (6 Cilindros)',
    'grind_crankshaft_8cyl' => 'Rectificado de Cigüeñal (8 Cilindros)',
    'straighten_crankshaft' => 'Enderezado de Cigüeñal',
    'build_up_flange' => 'Rellenar Ceja',
    'build_up_journal' => 'Rellenar Muñón',
    'dynamic_balancing_4cyl' => 'Balanceo Dinámico 4 Cilindros',
    'dynamic_balancing_6cyl' => 'Balanceo Dinámico 6 Cilindros',
    'dynamic_balancing_8cyl' => 'Balanceo Dinámico 8 Cilindros',
    
    // Servicios de Bielas
    'grind_connecting_rods' => 'Rectificado de Bielas (4 Bielas)',
    'align_rods_to_crankshaft' => 'Ajuste de Bielas a Cigüeñal (4 Bielas)',
    'assemble_pistons_press_fit' => 'Armado de Pistones a Presión (4)',
    'assemble_pistons_with_lock' => 'Armado de Pistones con Seguro (4)',
    
    'states' => [
        'is_budgeted' => 'Presupuestado',
        'is_authorized' => 'Autorizado',
        'is_completed' => 'Trabajo Realizado',
    ],
];
```

### resources/lang/en/orders.php (Actualizado)

```php
<?php

return [
    'status' => [
        'received' => 'Received',
        'awaiting_review' => 'Awaiting Review',
        'reviewed' => 'Reviewed',
        'awaiting_customer_approval' => 'Awaiting Customer Approval',
        'ready_for_work' => 'Ready for Work',
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'ready_for_delivery' => 'Ready for Delivery',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'paid' => 'Paid',
        'returned' => 'Returned',
        'not_paid' => 'Not Paid',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ],
    
    // ... existing translations
];
```

### resources/lang/es/orders.php (Actualizado)

```php
<?php

return [
    'status' => [
        'received' => 'Recibido',
        'awaiting_review' => 'Esperando Revisión',
        'reviewed' => 'Revisado',
        'awaiting_customer_approval' => 'Esperando Aprobación del Cliente',
        'ready_for_work' => 'Listo para Trabajo',
        'open' => 'Abierto',
        'in_progress' => 'En Progreso',
        'ready_for_delivery' => 'Listo para Entrega',
        'delivered' => 'Entregada',
        'completed' => 'Completada',
        'paid' => 'Pagada',
        'returned' => 'Devuelta',
        'not_paid' => 'No Pagada',
        'on_hold' => 'En Espera',
        'cancelled' => 'Cancelada',
    ],
    
    // ... existing translations
];
```

---

## 📊 Seeder Actualizado con i18n

### ServiceCatalogSeeder

```php
<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            // Cylinder Head Services
            [
                'service_key' => 'wash_cylinder_head_4cyl',
                'service_name_key' => 'services.wash_cylinder_head_4cyl',
                'item_type' => 'cylinder_head',
                'base_price' => 330.00,
                'display_order' => 1
            ],
            [
                'service_key' => 'wash_cylinder_heads_v6',
                'service_name_key' => 'services.wash_cylinder_heads_v6',
                'item_type' => 'cylinder_head',
                'base_price' => 660.00,
                'display_order' => 2
            ],
            
            // Engine Block Services
            [
                'service_key' => 'wash_block',
                'service_name_key' => 'services.wash_block',
                'item_type' => 'engine_block',
                'base_price' => 600.00,
                'display_order' => 1
            ],
            [
                'service_key' => 'grind_cylinder',
                'service_name_key' => 'services.grind_cylinder',
                'item_type' => 'engine_block',
                'base_price' => 245.00,
                'requires_measurement' => true,
                'display_order' => 2
            ],
            [
                'service_key' => 'weld_between_cylinders_qr25',
                'service_name_key' => 'services.weld_between_cylinders_qr25',
                'item_type' => 'engine_block',
                'base_price' => 800.00,
                'display_order' => 11
            ],
            
            // Crankshaft Services
            [
                'service_key' => 'polish_crankshaft_4cyl',
                'service_name_key' => 'services.polish_crankshaft_4cyl',
                'item_type' => 'crankshaft',
                'base_price' => 200.00,
                'display_order' => 1
            ],
            
            // Connecting Rods Services
            [
                'service_key' => 'grind_connecting_rods',
                'service_name_key' => 'services.grind_connecting_rods',
                'item_type' => 'connecting_rods',
                'base_price' => 680.00,
                'display_order' => 1
            ],
        ];
        
        foreach ($services as $service) {
            ServiceCatalog::create($service);
        }
    }
}
```

---

## 🎯 Uso en Controllers

### Ejemplo con Traducción Automática

```php
<?php

namespace App\Http\Controllers;

use App\Models\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $request->header('Accept-Language', 'en');
        app()->setLocale($locale);
        
        $services = ServiceCatalog::active()
            ->orderBy('item_type')
            ->orderBy('display_order')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'service_key' => $service->service_key,
                    'service_name' => $service->service_name, // Uses accessor with __()
                    'item_type' => $service->item_type->value,
                    'item_type_label' => $service->item_type->label(),
                    'base_price' => $service->base_price,
                    'net_price' => $service->net_price,
                    'requires_measurement' => $service->requires_measurement,
                ];
            });
        
        return response()->json(['services' => $services]);
    }
}
```

---

## ✅ Checklist de Correcciones

### Base de Datos
- [x] Nombres de columnas en inglés
- [x] Enum values en inglés
- [x] Agregar `service_name_key` para traducciones

### Modelos
- [x] Propiedades fillable en inglés
- [x] Accessors para traducciones automáticas
- [x] Métodos con nombres en inglés

### Enums
- [x] Cases en SCREAMING_SNAKE_CASE inglés
- [x] Values en snake_case inglés
- [x] Método `label()` usando __()

### Archivos de Traducción
- [x] `resources/lang/en/motor.php`
- [x] `resources/lang/es/motor.php`
- [x] `resources/lang/en/services.php`
- [x] `resources/lang/es/services.php`
- [x] Actualizar `resources/lang/en/orders.php`
- [x] Actualizar `resources/lang/es/orders.php`

### Seeders
- [x] ServiceCatalogSeeder con translation keys

---

## 🌍 Configuración de i18n en el Frontend

### Inertia Shared Data

```php
// app/Http/Middleware/HandleInertiaRequests.php

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'locale' => app()->getLocale(),
        'translations' => [
            'motor' => __('motor'),
            'services' => __('services'),
            'orders' => __('orders'),
        ],
    ]);
}
```

### Vue Component con i18n

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const translations = computed(() => page.props.translations);

const t = (key) => {
    const keys = key.split('.');
    let value = translations.value;
    
    for (const k of keys) {
        value = value?.[k];
    }
    
    return value || key;
};
</script>

<template>
    <div>
        <h3>{{ t('motor.fields.brand') }}</h3>
        <input v-model="form.brand" :placeholder="t('motor.fields.brand')" />
    </div>
</template>
```

---

## 📝 Notas Importantes

1. **Base de Datos**: Los nombres de columnas SIEMPRE en inglés
2. **Enum Values**: SIEMPRE en inglés (snake_case)
3. **Translation Keys**: Usar punto como separador (e.g., `motor.fields.brand`)
4. **API Responses**: Incluir tanto el valor como el label traducido
5. **Frontend**: Usar el header `Accept-Language` para cambiar idioma

---

**Documento Actualizado**: 2025  
**Proyecto**: Lumasachi Backend  
**Versión**: 2.0 (i18n Ready)
