# ✅ Post-Deploy Checklist — Motor Items

Use this checklist after deploying Phases 3–9.

Feature Flag and Access
- [ ] FEATURE_MOTOR_ITEMS_DISABLED set appropriately for the environment
- [ ] If rollout_date used, confirm current time is past the date or behaves as expected
- [ ] Customer users receive 403 for catalog endpoint; employees/admins/super admins receive 200

Catalog Endpoint (/api/v1/catalog/engine-options)
- [ ] Accept-Language=en returns English labels
- [ ] Accept-Language=es returns Spanish labels
- [ ] With item_type=engine_block returns ordered services and proper fields
- [ ] Without item_type returns full structure (item_types, components_by_type, services_by_type)
- [ ] X-Cache=MISS on first call and HIT on immediate repeat
- [ ] After updating a service_catalog record, next call is X-Cache=MISS (version bump)

Observers and History
- [ ] OrderItemObserver logs item is_received changes in OrderHistory
- [ ] OrderServiceObserver logs budgeted/authorized/completed changes in OrderHistory
- [ ] OrderObserver logs tracked field changes and sends notifications on status transitions (received, reviewed→awaiting_customer_approval, ready_for_delivery, delivered, paid)

Liquidation and Payment
- [ ] Completing a service triggers Order::recalculateTotals
- [ ] OrderMotorInfoObserver updates is_fully_paid based on down_payment and total_cost
- [ ] remaining_balance <= 0 implies fully paid

Notifications
- [ ] OrderReceivedNotification sent to customer when status=received
- [ ] OrderPaidNotification sent to customer when status=paid
- [ ] OrderAuditNotification sent to admins/super admins for relevant events

Logs and Health
- [ ] No unexpected errors in logs after smoke tests
- [ ] GET /api/v1/health returns healthy response (if applicable)
