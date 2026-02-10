# 🔄 Plan de Migración: orders.status a snake_case (OrderStatus)

Objetivo: migrar valores actuales (p. ej. "Open", "In Progress") a los nuevos valores snake_case en inglés, manteniendo integridad y minimizando downtime.

Estados actuales (ejemplo)
- "Open"
- "In Progress"
- "Ready for delivery"
- "Completed"
- "Delivered"
- "Paid"
- "Returned"
- "Not paid"
- "On hold"
- "Cancelled"

Nuevos valores (enum OrderStatus)
- open, in_progress, ready_for_delivery, completed, delivered, paid, returned, not_paid, on_hold, cancelled
- Nuevos del flujo: received, awaiting_review, reviewed, awaiting_customer_approval, ready_for_work

Estrategia (segura)

Pre-step: create a backup/snapshot of the data that will be mutated
- Before any schema or data changes, create a snapshot/export of the `orders` table (or a full DB dump) so you can restore pre-migration data if necessary. Example: use `pg_dump --table=orders` or a full `pg_dump` and retain the file alongside migration run metadata. Ensure the temporary column name `status_new` is included in the backup scope.

1) Crear columna temporal status_new (string)
2) Backfill con mapeo:
   - UPDATE orders SET status_new = 'open' WHERE status = 'Open';
   - UPDATE orders SET status_new = 'in_progress' WHERE status = 'In Progress';
   - UPDATE orders SET status_new = 'ready_for_delivery' WHERE status = 'Ready for delivery';
   - ... (resto del mapeo)
3) Validación: contar nulos / no mapeados
4) Remplazo: dropear columna status y renombrar status_new → status
5) Re-crear índices sobre status
6) Actualizar enum OrderStatus en código y casts en modelos

Rollback
- Invertir el proceso: crear status_old, mapear snake_case → títulos anteriores, renombrar

Notas
- Ejecutar en ventana de mantenimiento si la DB es de producción
- Registrar número de filas afectadas por cada mapeo
- Probar en staging antes del run en producción
