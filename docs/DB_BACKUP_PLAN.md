# 📦 Database Backup and Restore Plan

Objective: perform migration changes (including Pennant and new enums) with a safe backup and restore plan.

Database: the project uses PostgreSQL by default (credentials in .env).

## PostgreSQL

- Full backup:

  ```bash
  PGPASSWORD={{DB_PASSWORD}} pg_dump -h {{DB_HOST}} -U {{DB_USERNAME}} -F c -b -v -f backup_$(date +%Y%m%d_%H%M%S).dump {{DB_DATABASE}}
  ```

- Restore:

  ```bash
  PGPASSWORD={{DB_PASSWORD}} pg_restore -h {{DB_HOST}} -U {{DB_USERNAME}} -d {{DB_DATABASE}} -c -v backup_YYYYMMDD_hhmmss.dump
  ```

## Considerations

- Run backups before every batch of migrations.
- Test restore on a staging environment before applying to production.
- Automate backups in the deployment pipeline when possible.
