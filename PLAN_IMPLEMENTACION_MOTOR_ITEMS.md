# 🚀 Plan de Implementación — Ítems de Motor y Flujo de Cotización

Alineado con ARQUITECTURA_FINAL.md. Documento vivo para coordinar cambios de base de datos, modelos, endpoints, notificaciones, y pruebas (unitarias y de características) del nuevo flujo de recepción, revisión, cotización, autorización, trabajo realizado y entrega.

Actualizado: 2025-10-08

---

## 🎯 Alcance
- Modelo normalizado: order_motor_info (1:1), order_items (1:N), order_item_components (1:N), order_services (1:N), service_catalog (Catálogo maestro).
- Estados nuevos (inglés snake_case): received → awaiting_review → reviewed → awaiting_customer_approval → ready_for_work → ready_for_delivery → delivered.
- i18n completo (en/es) con translation keys.
- UUID como columna secundaria en todas las tablas nuevas.
- Cálculos: base_price, net_price (con IVA), totales PPTO/Aut./T.R., down_payment, total_cost, is_fully_paid.
- Endpoint de catálogo para UI: componentes y servicios por item_type, con i18n, caching y autorización para empleados.
- Pruebas unitarias y de características para cada entidad, observer, request y endpoint nuevo.

---

## 🏁 Fase 0 — Preparación (0.5 día)
- Rama de trabajo: feat/motor-items-architecture
- Feature flag: FEATURE_MOTOR_ITEMS=true (config/features.motor_items)
- Backup de BD antes de migraciones
- Revisión de valores actuales de OrderStatus para plan de migración a snake_case

Checklist
- [ ] Crear rama feat/motor-items-architecture
- [ ] Agregar feature flag
- [ ] Confirmar backup/restore plan

---

## 🗃️ Fase 1 — Base de datos y Enums (2–3 días)
Migraciones nuevas
1) order_motor_info
- order_id (FK UNIQUE), brand, liters, year, model, cylinder_count
- down_payment decimal(10,2), total_cost decimal(10,2), is_fully_paid bool
- center_torque, rod_torque, first_gap, second_gap, third_gap, center_clearance, rod_clearance
- uuid UNIQUE, timestamps, índices (order_id único, is_fully_paid)

2) order_items
- order_id (FK), item_type enum (OrderItemType), is_received bool
- uuid UNIQUE, timestamps, índices (order_id, item_type) y UNIQUE(order_id, item_type)

3) order_item_components
- order_item_id (FK), component_name string, is_received bool
- uuid UNIQUE, timestamps, índices y UNIQUE(order_item_id, component_name)

4) service_catalog
- service_key UNIQUE, service_name_key, item_type enum (OrderItemType)
- base_price decimal(10,2), tax_percentage decimal(5,2) default 16.00
- requires_measurement bool, is_active bool, display_order int
- uuid UNIQUE, timestamps, índices (item_type, is_active), (display_order)

5) order_services
- order_item_id (FK), service_key (FK lógica a service_catalog.service_key)
- measurement string nullable, is_budgeted bool, is_authorized bool, is_completed bool
- notes text nullable, base_price decimal(10,2), net_price decimal(10,2)
- uuid UNIQUE, timestamps, índices por estados y service_key

Enums
- OrderItemType: cylinder_head, engine_block, crankshaft, connecting_rods, others
- OrderStatus (extensión): received, awaiting_review, reviewed, awaiting_customer_approval, ready_for_work (además de open, in_progress, ready_for_delivery, delivered, completed, paid, returned, not_paid, on_hold, cancelled)

Migración de valores de estado (backfill seguro)
- Agregar columna temporal orders.status_new (string)
- Mapear valores actuales a snake_case
- Reemplazar columna status por status_new o alterar enum según soporte
- Reindexar y validar

Seeders
- ServiceCatalogSeeder con servicios documentados (cabeza, block, cigüeñal, bielas), base_price, tax_percentage, requires_measurement, display_order

Tests (DB/Unit-light)
- Verificar FKs/índices/UNIQUE en nuevas tablas
- Verificar valores de OrderItemType vs seeds
- Verificar mapeo y persistencia de estados en orders.status

Checklist
- [ ] Crear 5 migraciones con UUIDs
- [ ] Actualizar OrderStatus y OrderItemType
- [ ] Seeder de service_catalog
- [ ] Tests de migración y enums

---

## 🧩 Fase 2 — Modelos y Relaciones (2–3 días)
Modelos
- OrderMotorInfo: casts decimales/booleanos; accessors remaining_balance; helpers hasPendingPayment
- OrderItem: casts is_received y item_type; relaciones components, services
- OrderItemComponent: accessor component_label (i18n)
- OrderService: casts booleans/decimals; belongsTo catalog por service_key; accessor service_name (i18n)
- ServiceCatalog: scopes active/forItemType; net_price calculado; i18n via service_name_key
- Order (actualizado): motorInfo (hasOne), items (hasMany), services (hasManyThrough); accessors total_budgeted, total_authorized, total_completed, remaining_balance; método updateTotalCost()

Enums
- OrderItemType con label(), getValues(), getComponents() por tipo

Factories
- Factories para OrderMotorInfo, OrderItem, OrderItemComponent, OrderService, ServiceCatalog con UUID

Tests unit (modelos y enums)
- OrderMotorInfoTest: casts, remaining_balance, hasPendingPayment
- OrderItemTest: relaciones y cast item_type
- OrderItemComponentTest: component_label i18n
- OrderServiceTest: service_name i18n, net_price, relaciones
- ServiceCatalogTest: scopes, net_price, i18n
- OrderItemTypeTest: valores, getComponents, label
- OrderStatusTest: valores y label
- OrderTest: total_budgeted/authorized/completed, remaining_balance, updateTotalCost

Checklist
- [ ] Implementar modelos y relaciones
- [ ] Factories
- [ ] Tests unit verdes

---

## 🕵️ Fase 3 — Observers, OrderHistory y Notificaciones (2 días)
Observers
- OrderObserver: transiciones y notificaciones en received, reviewed, ready_for_delivery, delivered; registro en OrderHistory
- OrderItemObserver y OrderServiceObserver: registrar cambios (creación, flags, medidas/precios) con contexto

Notificaciones
- OrderReceivedNotification, OrderReviewedNotification, OrderReadyForDeliveryNotification, OrderDeliveredNotification (cliente)
- OrderAuditNotification (admins/super admins)
- Encoladas (queue) y con database notifications

Tests feature
- OrderObserverTest: historial por transición + notificaciones
- OrderServiceObserverTest: historial por flags + updateTotalCost al completar servicios

Checklist
- [ ] Observers implementados y registrados
- [ ] Notificaciones y plantillas
- [ ] Tests feature

---

## 📚 Fase 4 — Endpoint de Catálogo para UI (2–3 días)
Ruta
- GET /api/catalog/engine-options

Autorización
- Permitir: roles employee, admin, super_admin
- Denegar: customer/guest

Headers
- Accept-Language: en | es (default en)

Query Params
- item_type (opcional): filtra a un tipo específico

Respuesta (con item_type)
- item_type, item_type_label
- components: [{ key, label }]
- services: [{ service_key, service_name, base_price, net_price, requires_measurement, display_order, item_type }]

Respuesta (sin item_type)
- item_types: [{ key, label }]
- components_by_type: { engine_block: [...], cylinder_head: [...], ... }
- services_by_type: { engine_block: [...], cylinder_head: [...], ... }

Caching
- Cache por (locale, item_type) con invalidación al actualizar ServiceCatalog (observer/evento)

Tests feature (CatalogControllerTest)
- 200 para employee con item_type
- 403 para customer
- i18n: Accept-Language es/en retorna labels correctos
- Ordenación por display_order
- Filtrado por item_type (solo servicios del tipo)
- Campos requires_measurement, base_price y net_price correctos
- Cache: segunda llamada sin hitting DB (verificable con spies o TTL bajo)

Checklist
- [ ] Controlador y ruta
- [ ] Policy/guard de autorización
- [ ] Cache con invalidación
- [ ] Tests feature verdes

---

## 🧮 Fase 5 — Controladores, Requests y Resources (2–3 días)
Controladores
- OrderMotorInfoController: store/update (incluye down_payment)
- OrderItemController: crear item (item_type) y marcar is_received
- OrderServiceController: agregar servicios (service_key), toggles is_budgeted/is_authorized/is_completed, bulk update opcional, recalcular totales al completar
- CatalogController: endpoint de catálogo (Fase 4)

Form Requests
- Store/UpdateOrderMotorInfoRequest
- StoreOrderItemRequest
- StoreOrderServiceRequest
- BulkUpdateOrderServicesRequest

API Resources
- OrderMotorInfoResource, OrderItemResource, OrderServiceResource con labels i18n

Tests feature
- Motor info: validaciones y persistencia
- Items: creación por item_type y unicidad (unique order_id+item_type)
- Services: alta por service_key válido, flags, cálculo net_price desde catálogo si no se envía, bulk toggles
- Recalculo de total_cost e is_fully_paid tras completar servicios y cambiar down_payment

Checklist
- [ ] Controladores y Requests
- [ ] Resources con i18n
- [ ] Tests feature

---

## 🔁 Fase 6 — Lógica de Estado y Cálculo Financiero (2 días)
Servicios de dominio
- OrderStatusTransitionService: transiciones válidas, disparo de notificaciones; movimientos automáticos: received→awaiting_review al registrar ítems; reviewed→awaiting_customer_approval tras notificar al cliente, etc.
- OrderCalculatorService: totales PPTO/Aut./T.R., updateTotalCost, remaining_balance

Tests unit
- OrderStatusTransitionServiceTest
- OrderCalculatorServiceTest

Checklist
- [ ] Servicios implementados
- [ ] Tests unit

---

## 🛰️ Fase 7 — Endpoint Público de Tracking (1–2 días)
Ruta
- GET /api/orders/public/track?uuid=&created_at=

Respuesta
- Estado actual (value + label), totales PPTO/Aut./T.R., down_payment, remaining_balance
- Items y servicios aprobados/completados
- Adjuntos (si aplica)

Seguridad
- Rate limiting, sanitización de campos, no exponer datos sensibles

Tests feature
- 200 con uuid/fecha válidos; 404 si no coincide
- i18n labels en respuesta

Checklist
- [ ] Endpoint público
- [ ] Tests feature

---

## 🌱 Fase 8 — Seeds y Datos Iniciales (1 día)
- Completar ServiceCatalogSeeder con lista de servicios de cabeza, block, cigüeñal, bielas (en/es)
- Validar display_order y requires_measurement

Tests feature
- CatalogControllerTest valida que aparecen servicios sembrados por item_type e i18n

Checklist
- [ ] Seeder completo
- [ ] Tests actualizados

---

## ⚡ Fase 9 — Rendimiento, Cache e Índices (0.5–1 día)
- Confirmar índices compuestos:
  - order_services(order_item_id, is_budgeted, is_authorized, is_completed)
  - order_items(order_id, item_type)
- Cache endpoint de catálogo; invalidación en update/create de ServiceCatalog

Tests (opcionales)
- Smoke de performance local (< 50ms cache caliente)

Checklist
- [ ] Índices confirmados
- [ ] Cache validado

---

## 📄 Fase 10 — Documentación y Despliegue (0.5–1 día)
- Mini OpenAPI de endpoints nuevos
- Guía de migración y rollback (especialmente enum status)
- Feature flag por entorno (staging → producción)
- Checklist post-deploy: estados traducidos, cálculos, notificaciones

Checklist
- [ ] Docs API
- [ ] Guía de despliegue/rollback
- [ ] Post-deploy checks

---

## ✅ Criterios de Aceptación Clave
- Endpoint /api/catalog/engine-options:
  - Autorización por rol (employee/admin/super_admin)
  - i18n por Accept-Language
  - components desde OrderItemType::getComponents() con labels traducidos
  - services desde service_catalog, filtrados por item_type, ordenados por display_order, con base_price y net_price correctos
- Flujo de estados operativo con notificaciones:
  - Creación de ítems → awaiting_review
  - Presupuesto completo → reviewed → notifica cliente y admins → awaiting_customer_approval
  - Aprobación → ready_for_work
  - Trabajo realizado (is_completed) actualiza total_cost → ready_for_delivery → notifica cliente
  - Entregada → notifica cliente y admins
- Liquidación:
  - remaining_balance = total_completed − down_payment
  - is_fully_paid se actualiza automáticamente cuando remaining_balance ≤ 0

---

## 🧪 Matriz de Tests (archivos sugeridos)
Unit
- tests/Unit/app/Enums/OrderItemTypeTest.php
- tests/Unit/app/Enums/OrderStatusTest.php
- tests/Unit/app/Models/OrderMotorInfoTest.php
- tests/Unit/app/Models/OrderItemTest.php
- tests/Unit/app/Models/OrderItemComponentTest.php
- tests/Unit/app/Models/OrderServiceTest.php
- tests/Unit/app/Models/ServiceCatalogTest.php
- tests/Unit/app/Models/OrderTest.php
- tests/Unit/app/Services/OrderStatusTransitionServiceTest.php
- tests/Unit/app/Services/OrderCalculatorServiceTest.php

Feature
- tests/Feature/app/Observers/OrderObserverTest.php (ampliado)
- tests/Feature/app/Observers/OrderServiceObserverTest.php
- tests/Feature/app/Http/Controllers/CatalogControllerTest.php
- tests/Feature/app/Http/Controllers/OrderMotorInfoControllerTest.php
- tests/Feature/app/Http/Controllers/OrderItemControllerTest.php
- tests/Feature/app/Http/Controllers/OrderServiceControllerTest.php
- tests/Feature/app/Http/Controllers/PublicOrderTrackingControllerTest.php

---

## 🧭 Riesgos y Mitigación
- Cambio de enum en orders.status:
  - Usar columna temporal + backfill; probar en staging; preparar rollback
- i18n incompleto:
  - Tests bilingües para endpoint y labels; checklist de claves requeridas
- Precios
  - net_price persistido en order_services para “congelar” cotización frente a cambios futuros del catálogo

---

## ⏱️ Timeline Estimado
- Desarrollo: 12–16 días
- QA: 3–4 días
- Total: 15–20 días (dentro de ventana 20–22 días)

---

## ▶️ Próximos Pasos
- Iniciar Fase 1 (migraciones) con TDD: crear tests de catálogo (endpoint) fallando, luego implementar
- Preparar mapeo de estados y plan de backfill en staging
- Sembrar catálogo mínimo para pruebas funcionales

---

Este documento es vivo. Actualizar checklists y pruebas conforme se avance por fases.
