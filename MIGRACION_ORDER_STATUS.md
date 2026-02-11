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
   - UPDATE orders SET status_new = 'Open' WHERE status = 'Open';
   - UPDATE orders SET status_new = 'In Progress' WHERE status = 'In Progress';
   - UPDATE orders SET status_new = 'Ready for Delivery' WHERE status = 'Ready for delivery';
   - ... (resto del mapeo)
3) Validación: contar nulos / no mapeados

   ```sql
   -- Total de filas donde status_new quedó nulo (debe ser 0)
   SELECT COUNT(*) AS null_count FROM orders WHERE status_new IS NULL;

   -- Desglose por status original de las filas no mapeadas
   SELECT status, COUNT(*) AS cnt
   FROM orders
   WHERE status_new IS NULL
   GROUP BY status
   ORDER BY cnt DESC;

   -- Verificación cruzada: totales deben coincidir
   SELECT
     (SELECT COUNT(*) FROM orders) AS total,
     (SELECT COUNT(*) FROM orders WHERE status_new IS NOT NULL) AS mapped;
   ```

4) Envolver los pasos críticos en una transacción:

   ```sql
   BEGIN;

   -- Backfill (paso 2)
   UPDATE orders SET status_new = 'Open' WHERE status = 'Open';
   UPDATE orders SET status_new = 'In Progress' WHERE status = 'In Progress';
   UPDATE orders SET status_new = 'Ready for Delivery' WHERE status = 'Ready for delivery';
   -- ... (resto del mapeo)

   -- Validación (paso 3) — abortar si hay nulos
   DO $$
   BEGIN
     IF EXISTS (SELECT 1 FROM orders WHERE status_new IS NULL) THEN
       RAISE EXCEPTION 'Existen filas sin mapear en status_new. Abortando.';
     END IF;
   END $$;

   -- Swap: dropear columna vieja y renombrar
   ALTER TABLE orders DROP COLUMN status;
   ALTER TABLE orders RENAME COLUMN status_new TO status;

   -- Re-crear índices
   CREATE INDEX idx_orders_status ON orders (status);

   COMMIT;
   ```

   > **Nota**: Si la transacción falla, todas las operaciones se revierten automáticamente.
   > Coordinar el deploy del código (OrderStatus enum y model casts) inmediatamente
   > después del COMMIT exitoso. Si el COMMIT falla, el código existente sigue funcionando
   > sin cambios.

5) Actualizar enum OrderStatus en código y casts en modelos

Rollback
- Invertir el proceso: crear status_old, mapear snake_case → títulos anteriores, renombrar

Notas
- Ejecutar en ventana de mantenimiento si la DB es de producción
- Registrar número de filas afectadas por cada mapeo
- Probar en staging antes del run en producción
