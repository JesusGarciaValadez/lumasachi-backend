# 🚀 Deployment Guide — Motor Items Phases 3–9

This guide covers deploying the new motor items features (catalog, observers, liquidation/payment recalculation, notifications) and enabling the feature flag per environment.

Prereqs
- Database backup/restore plan ready.
- Review MIGRACION_ORDER_STATUS.md if planning to migrate legacy order status values to snake_case in a later window.
- Ensure cache store is configured (database/redis/memcached) for catalog caching.

Feature Flag
- Env variables (see config/features.php and .env.example):
  - FEATURE_MOTOR_ITEMS_DISABLED=false|true
  - FEATURE_MOTOR_ITEMS_ROLLOUT_DATE="YYYY-MM-DD HH:MM:SS" (optional)
- Behavior:
  - If disabled=true, only admins/super admins can access endpoints (via App\Features\MotorItems::before).
  - If rollout_date is in the past, the feature is considered enabled for everyone (unless disabled=true).

Deployment Steps
1) Migrate database
   - php artisan migrate --force
   - New tables: order_motor_info, order_items, order_item_components, service_catalog, order_services
   - Indexes confirmed:
     - order_items(order_id, item_type) UNIQUE(order_id, item_type)
     - order_services(order_item_id, is_budgeted, is_authorized, is_completed)
     - service_catalog(item_type, is_active), display_order
2) Seed service catalog (optional in non-prod)
   - php artisan db:seed --class=Database\\Seeders\\ServiceCatalogSeeder --force
3) Configure feature flag by environment
   - Staging: FEATURE_MOTOR_ITEMS_DISABLED=false, set ROLLOUT_DATE to now for full access
   - Production: initially set FEATURE_MOTOR_ITEMS_DISABLED=true to restrict to staff; later set to false or set ROLLOUT_DATE to a future/past date for controlled rollout
4) Warm catalog cache (optional)
   - Call GET /api/v1/catalog/engine-options (with and without item_type) per locale to prime cache
   - Cache is versioned; any create/update/delete on service_catalog bumps the version automatically (ServiceCatalogObserver)

Rollback Steps
- If only code deploy:
  - Re-enable disable flag: FEATURE_MOTOR_ITEMS_DISABLED=true
- If migrations must be rolled back:
  - php artisan migrate:rollback --step=1 (repeat as needed; ensure impact is acceptable)
- If status migration was executed:
  - Follow MIGRACION_ORDER_STATUS.md rollback plan (map snake_case back to legacy values via status_old, then rename)

Validation (Smoke Tests)
- GET /api/v1/catalog/engine-options?item_type=engine_block (as employee)
  - Expect 200, X-Cache: MISS first then HIT on second request
  - Services ordered by display_order and translated by Accept-Language
- Toggle a service_catalog row (update display_order or is_active)
  - Verify subsequent call returns X-Cache: MISS and new data
- Complete an order service (is_completed=true) in a non-prod DB
  - Confirm Order::recalculateTotals updates total_cost and is_fully_paid (via OrderMotorInfoObserver)
- Change order status to PAID in a safe test order
  - Confirm notifications are queued/created (email/log/database depending on env)

Operational Notes
- Catalog cache keys are versioned by service_catalog:version and include (locale,item_type); invalidated via observer.
- Feature gating uses Laravel Pennant with App\Features\MotorItems; persistence via PENNANT_STORE=database (see .env.example).
- Tests reference Accept-Language header; default is en.
