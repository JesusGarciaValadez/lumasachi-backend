# Objetivo

Definir, con base en el código actual del módulo de órdenes e historial, el contrato de campos/endpoints para frontend y
comparar alternativas arquitectónicas para cubrir el requerimiento completo sin afectar el histórico de órdenes.

## Estado actual auditado en backend

El backend ya implementa un modelo relacional para motor/ítems/servicios y un flujo lifecycle por etapas. Referencias
base:

* `database/migrations/2025_10_08_170001_create_order_motor_info_table.php`
* `database/migrations/2025_10_08_170002_create_order_items_table.php`
* `database/migrations/2025_10_08_170003_create_order_item_components_table.php`
* `database/migrations/2025_10_08_170004_create_service_catalog_table.php`
* `database/migrations/2025_10_08_170005_create_order_services_table.php`
* `app/Services/OrderLifecycleService.php`
* `app/Http/Controllers/OrderController.php`
* `app/Http/Controllers/PublicOrderController.php`
* `app/Observers/OrderObserver.php`
* `app/Observers/OrderServiceObserver.php`
* `routes/api.php`

## Modelo de datos vigente para frontend

### 1) Datos generales del motor (ya soportados)

Se guardan en `order_motor_info`:

* `brand`, `liters`, `year`, `model`, `cylinder_count`
* `down_payment` (decimal 10,2)
* `total_cost` (decimal 10,2)
* `is_fully_paid` (boolean)
* Campos técnicos ya existentes pero hoy no expuestos en el request de creación: `center_torque`, `rod_torque`,
  `first_gap`, `second_gap`, `third_gap`, `center_clearance`, `rod_clearance`

### 2) Ítems recibidos (ya soportados)

Se guardan en `order_items`:

* `item_type` enum: `cylinder_head`, `engine_block`, `crankshaft`, `connecting_rods`, `others`
* `is_received`

### 3) Componentes por ítem (ya soportados)

Se guardan en `order_item_components`:

* `component_name` (clave técnica)
* `is_received`
  Las claves disponibles por tipo están en `app/Enums/OrderItemType.php`.

### 4) Trabajos/servicios (ya soportados)

Se guardan en `order_services`:

* `service_key`
* `measurement`
* `is_budgeted` (PPTO)
* `is_authorized` (Aut.)
* `is_completed` (T.R.)
* `notes`
* `base_price`, `net_price`
  La lista y precios base/neto por servicio salen del catálogo `service_catalog` y del endpoint de catálogo.

## Flujo lifecycle vigente

Implementado en `OrderLifecycleService` + `OrderObserver`:

1. Creación de orden: se crea en `Received` y se mueve automáticamente a `Awaiting Review`.
2. Presupuesto (`/budget`): crea/actualiza servicios con `is_budgeted=true`, cambia a `Reviewed` y se auto-mueve a
   `Awaiting Customer Approval`.
3. Aprobación cliente (`/customer-approval`): marca `is_authorized=true` en servicios seleccionados, opcionalmente
   actualiza `down_payment`, mueve a `Ready for Work`.
4. Trabajo realizado (`/work-completed`): marca `is_completed=true` en servicios seleccionados.
5. Listo para entrega (`/ready-for-delivery`): mueve a `Ready for Delivery`.
6. Entrega (`/deliver`): mueve a `Delivered`. El histórico se registra en `order_histories` por observers
   (`OrderObserver`, `OrderServiceObserver`, `OrderItemObserver`).

## Endpoints y payloads para frontend (vigentes)

### Catálogo de opciones (combos y precios)

`GET /api/v1/catalog/engine-options?item_type={item_type}`
Sin `item_type` retorna catálogo completo por tipo. Con `item_type` retorna componentes + servicios de ese tipo.
Respuesta útil para UI:

* `components[]: { key, label }`
* `services[]: { service_key, service_name, base_price, net_price, requires_measurement, display_order, item_type }`

### Crear orden con motor e ítems

`POST /api/v1/orders`
Payload:

```json
{
  "customer_id": 10,
  "title": "Rectificado Block QR25",
  "description": "Cliente deja block a revisión",
  "priority": "High",
  "assigned_to": 7,
  "motor_info": {
    "brand": "Nissan",
    "liters": "2.5",
    "year": "2019",
    "model": "Altima",
    "cylinder_count": "4",
    "down_payment": 0
  },
  "items": [
    {
      "item_type": "engine_block",
      "components": ["camshaft", "bearing_caps", "cap_bolts"]
    }
  ]
}
```

### Registrar trabajos presupuestados (PPTO)

`POST /api/v1/orders/{order_uuid}/budget`
Payload:

```json
{
  "services": [
    {
      "order_item_id": 21,
      "service_key": "wash_block",
      "measurement": null
    },
    {
      "order_item_id": 21,
      "service_key": "weld_between_cylinders_qr25",
      "measurement": null
    }
  ]
}
```

### Aprobación del cliente (Aut.) + anticipo

`POST /api/v1/orders/{order_uuid}/customer-approval`
Payload:

```json
{
  "authorized_service_ids": [101, 102, 104],
  "down_payment": 1500.00
}
```

### Trabajo realizado (T.R.)

`POST /api/v1/orders/{order_uuid}/work-completed`
Payload:

```json
{
  "completed_service_ids": [101, 104]
}
```

### Cambios de estado finales

`POST /api/v1/orders/{order_uuid}/ready-for-delivery` (sin body)
`POST /api/v1/orders/{order_uuid}/deliver` (sin body)

### Consulta de detalle para pantallas privadas

`GET /api/v1/orders/{order_uuid}`
Retorna `motor_info`, `items` (con `components`) y `services`.

### Historial y adjuntos en entorno autenticado

* `GET /api/v1/orders/{order_uuid}/history`
* `GET /api/v1/orders/{order_uuid}/attachments`

### Tracking público por UUID + fecha

`POST /api/v1/orders/track`
Payload:

```json
{
  "uuid": "7a9b0d4f-....",
  "created_date": "2026-02-12"
}
```

## Mapeo de requerimiento funcional a claves backend

### Ítems

* Cabeza → `cylinder_head`
* Block → `engine_block`
* Cigüeñal → `crankshaft`
* Bielas → `connecting_rods`
* Otros → `others`

### Banderas de la hoja de trabajo

* PPTO → `is_budgeted`
* Aut. → `is_authorized`
* T.R. → `is_completed`
* Medida → `measurement`
* Texto libre por trabajo → `notes` (campo existe en DB, no expuesto actualmente en request de presupuesto)

### Montos

* Anticipo → `order_motor_info.down_payment` (decimal)
* Total final cobrado (según trabajo realizado) → `order_motor_info.total_cost` (calculado por `recalculateTotals()` con
  servicios completados)

## Brechas actuales contra el requerimiento

1. El endpoint público de tracking no devuelve histórico ni adjuntos de forma directa (requisito pide ver ambos con
   UUID+fecha).
2. No existe formulario Inertia+Vue público para tracking (sólo API pública).
3. No hay endpoint dedicado para actualizar campos técnicos de `order_motor_info` tras la creación (torques, gaps,
   clearances, anticipo posterior).
4. `OrderServiceResource` no expone `order_item_id` ni `notes`, lo que dificulta agrupar y editar por ítem en frontend.
5. En `submitBudget` no se recibe `notes` por servicio, aunque la columna existe.
6. La auto-transición `Reviewed -> Awaiting Customer Approval` se hace con `updateQuietly`, por lo que la transición
   automática no queda con el mismo nivel de trazabilidad detallada en historial de estado.

## Alternativas arquitectónicas

### Opción A (recomendada): Mantener modelo relacional actual + snapshots inmutables por etapa

Descripción:

* Se conserva la estructura actual (`orders`, `order_motor_info`, `order_items`, `order_services`, `order_histories`).
* Se agrega una capa de snapshots para congelar el estado comercial en tres hitos: Presupuestado, Autorizado, Realizado.
  Propuesta mínima:
* Nueva tabla `order_quote_snapshots`:
    * `order_id`, `stage` (`budgeted|authorized|completed`), `payload_json`, `base_total`, `net_total`, `created_by`,
      timestamps.
* Se crea snapshot al cerrar cada endpoint lifecycle (`budget`, `customer-approval`, `work-completed`). Ventajas:
* No rompe histórico existente.
* Mantiene auditoría detallada y además deja “foto” inmutable por etapa.
* Fácil de consumir para frontend/reportes y para explicar diferencias PPTO vs Aut. vs T.R. Desventajas:
* Duplica algo de información (intencional, para trazabilidad).
* Requiere disciplina para crear snapshot en cada transición relevante. Pasos de implementación:

1. Migración + modelo `OrderQuoteSnapshot`.
2. Hook en `OrderLifecycleService` para generar snapshot por etapa.
3. Endpoint para leer snapshots por orden.
4. Extender tracking público para incluir resumen + histórico + adjuntos metadata.
5. Tests de integridad: snapshot coincide con flags/servicios del momento.

### Opción B: Usar sólo tablas actuales y reconstruir desde flags + order_histories

Descripción:

* No se agregan tablas nuevas.
* Se reconstruyen PPTO/Aut./T.R. consultando flags actuales e historial. Ventajas:
* Menor esfuerzo inicial.
* Cero cambios estructurales. Desventajas:
* Costoso y frágil para reconstruir estados pasados con precisión comercial.
* Complejidad mayor en consultas y mayor riesgo de discrepancias históricas. Pasos de implementación:

1. Ajustar resources para exponer campos faltantes (`order_item_id`, `notes`).
2. Agregar endpoints de consulta agregada por etapa.
3. Generar reportes con reconstrucción desde histórico.

### Opción C: Event Sourcing completo

Descripción:

* Cada acción genera evento inmutable (`order_created`, `budget_submitted`, `customer_approved`, `work_completed`, etc.)
  y se proyecta a tablas de lectura. Ventajas:
* Máxima trazabilidad y auditabilidad.
* Excelente para histórico y analítica avanzada. Desventajas:
* Complejidad y tiempo de implementación significativamente mayores.
* Cambios profundos de arquitectura. Pasos de implementación:

1. Infraestructura de eventos y almacenamiento.
2. Proyecciones para vistas de frontend.
3. Migración gradual desde flujo actual.

## Recomendación final

Adoptar Opción A (extensión incremental sobre el modelo actual + snapshots inmutables). Razones:

* Cumple el requerimiento completo sin romper ni reemplazar el histórico actual.
* Requiere cambios acotados y compatibles con endpoints existentes.
* Mejora la consumibilidad para frontend (cotización por etapas) y auditoría.

## Plan de implementación recomendado (Opción A)

### Fase 1: Contrato API para frontend

* Extender `OrderServiceResource` con `order_item_id`, `notes`, `service_name`.
* Permitir `services.*.notes` en `SubmitBudgetRequest`.
* Agregar endpoint `PATCH /api/v1/orders/{order_uuid}/motor-info` para actualizar anticipo/campos técnicos.

### Fase 2: Snapshots de cotización

* Crear `order_quote_snapshots`.
* Generar snapshots automáticos en `budget`, `customer-approval`, `work-completed`.

### Fase 3: Tracking público completo

* Extender `POST /api/v1/orders/track` para incluir:
    * Resumen de orden
    * Historial filtrado
    * Adjuntos (metadata + URL firmada/segura)
* Aplicar rate limiting y payload mínimo seguro.

### Fase 4: Inertia + Vue (formulario público)

* Crear ruta web pública para formulario UUID + fecha.
* Consumir endpoint público y mostrar: estado, histórico, adjuntos, resumen económico por etapa.

### Fase 5: Pruebas

* Feature tests de payload/validación para nuevos campos y endpoint motor-info.
* Feature tests de snapshots por etapa.
* Feature tests de tracking público con histórico + adjuntos.
* Confirmar que `order_histories` sigue registrando cambios de estado/flags.

## Definición de campos frontend por pantalla

### Pantalla 1: Crear orden

* Datos base: `customer_id`, `title`, `description`, `priority`, `assigned_to`.
* Motor: `brand`, `liters`, `year`, `model`, `cylinder_count`.
* Ítems: selección de `item_type` + `components[]` por ítem.
* Anticipo inicial: `down_payment`.

### Pantalla 2: Revisión (PPTO)

* Tabla por ítem con columnas: trabajo, medida, PPTO, Aut., T.R., notas.
* Captura principal: `services[]` con `order_item_id`, `service_key`, `measurement`, `notes`.

### Pantalla 3: Aprobación cliente (Aut.)

* Selección de servicios autorizados `authorized_service_ids[]`.
* Ajuste de anticipo `down_payment`.

### Pantalla 4: Trabajo realizado (T.R.)

* Selección de servicios completados `completed_service_ids[]`.

### Pantalla 5: Seguimiento público (Inertia + Vue)

* Input: `uuid`, `created_date`.
* Visualización: estado actual, timeline histórico, adjuntos y resumen económico por etapa.

## Criterio de éxito

La solución queda lista cuando frontend puede capturar y mostrar todo el ciclo (recepción, revisión, aprobación,
trabajo, entrega) con datos económicos por etapa y trazabilidad histórica consistente, sin degradar `order_histories` ni
las notificaciones actuales.
