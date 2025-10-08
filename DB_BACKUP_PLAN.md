# 📦 Plan de Respaldo y Restauración de BD

Objetivo: realizar cambios de migración (incluyendo Pennant y nuevos enums) con un plan de respaldo y restauración seguro.

Base de datos: por defecto el proyecto usa PostgreSQL en .env.

PostgreSQL
- Respaldo completo:
  - PGPASSWORD={{DB_PASSWORD}} pg_dump -h {{DB_HOST}} -U {{DB_USERNAME}} -F c -b -v -f backup_$(date +%Y%m%d_%H%M%S).dump {{DB_DATABASE}}
- Restauración:
  - PGPASSWORD={{DB_PASSWORD}} pg_restore -h {{DB_HOST}} -U {{DB_USERNAME}} -d {{DB_DATABASE}} -c -v backup_YYYYMMDD_hhmmss.dump

Consideraciones
- Ejecutar respaldos antes de cada lote de migraciones.
- Probar restauración en staging.
- Automatizar en pipeline de despliegue si es posible.
