# Lumasachi Backend

Aplicación Laravel para gestionar empresas, usuarios y órdenes.

## Instalación local

### Requisitos

- Git.
- Docker Desktop (o Docker Engine) con Docker Compose.
- PHP 8.5 y Composer para la primera instalación de dependencias. Después, ejecuta los comandos de PHP y Composer
  mediante Sail.

El contenedor de desarrollo usa PHP 8.5 y Node 24. Docker Compose también inicia PostgreSQL 17, Redis, Memcached,
Mailpit y Selenium.

### Preparar el entorno

1. Clona el repositorio y entra en su directorio.
2. Crea la configuración local:

   ```sh
   cp .env.example .env
   ```

3. En `.env`, configura como mínimo estos valores para usar PostgreSQL con Compose. Son valores locales; no los
   reutilices en otros entornos.

   ```dotenv
   APP_URL=https://localhost
   DB_CONNECTION=pgsql
   DB_HOST=pgsql
   DB_PORT=5432
   DB_DATABASE=lumasachi
   DB_USERNAME=sail
   DB_PASSWORD=secret

   TENANCY_CENTRAL_CONNECTION=pgsql
   TENANCY_TENANT_TEMPLATE_CONNECTION=tenant_template
   TENANCY_DB_PROVISIONER_HOST=pgsql
   TENANCY_DB_PROVISIONER_PORT=5432
   TENANCY_DB_PROVISIONER_DATABASE=postgres
   TENANCY_DB_PROVISIONER_USERNAME=lumasachi_provisioner
   TENANCY_DB_PROVISIONER_PASSWORD=local-only-provisioner-password
   ```

   El ejemplo de entorno del repositorio aún indica SQLite y no define todos los valores que Compose necesita para
   inicializar PostgreSQL; por eso es necesario cambiar estas opciones localmente.

4. Instala Composer una vez en el host:

   ```sh
   composer install
   ```

   Este paso inicial es necesario porque la imagen de Sail se construye desde `vendor/laravel/sail`. Una vez que exista
   `vendor/bin/sail`, usa Sail para los comandos posteriores de PHP, Composer y Node.

### Iniciar servicios y preparar la base

1. Inicia los contenedores:

   ```sh
   vendor/bin/sail up -d --build
   ```

2. Crea el rol de aprovisionamiento de tenants en el PostgreSQL local. La aplicación requiere que sea distinto del
   usuario de la conexión central, con `CREATEDB` y `CREATEROLE`, pero sin `SUPERUSER`. Con los valores de arriba, abre
   una consola de PostgreSQL:

   ```sh
   vendor/bin/sail exec pgsql psql -U sail -d postgres
   ```

   En `psql`, ejecuta lo siguiente y usa para la clave el mismo valor local de `TENANCY_DB_PROVISIONER_PASSWORD`:

   ```sql
   CREATE ROLE lumasachi_provisioner
       LOGIN CREATEDB CREATEROLE NOSUPERUSER NOREPLICATION
       PASSWORD 'local-only-provisioner-password';
   ```

   Sal de `psql` con `\q`. Si cambiaste `DB_USERNAME`, usa ese usuario en el comando anterior.

3. Genera la clave de la aplicación, migra la base central e instala y compila el frontend:

   ```sh
   vendor/bin/sail artisan key:generate
   vendor/bin/sail artisan tenancy:migrate-central
   vendor/bin/sail yarn install
   vendor/bin/sail yarn build
   ```

   No ejecutes `migrate --seed` como paso genérico de instalación: `DatabaseSeeder` conserva datos operativos de ejemplo
   de la estructura anterior. El flujo de alta de cada empresa ejecuta sus migraciones y carga sus datos de catálogo
   predeterminados.

### Crear acceso local y una empresa

El repositorio todavía no incluye un comando de instalación para crear el primer Super Administrador central. En una
base local recién migrada, créalo con la factory existente:

```sh
vendor/bin/sail artisan tinker
```

En Tinker:

```php
App\Models\CentralSuperAdministrator::factory()->create([
    'first_name' => 'Local',
    'last_name' => 'Administrator',
    'email' => 'superadmin@local.test',
    'password' => 'cambia-esta-clave-local',
]);
```

Usa esta cuenta solo en tu entorno local. Luego:

1. Abre [https://localhost/login](https://localhost/login) e inicia sesión con la cuenta local.
2. En la administración central, crea una empresa con `localhost` como dominio padre; por ejemplo, el subdominio `acme`
   crea `acme.localhost`.
3. Revisa la invitación del primer Administrador de la empresa en
   Mailpit: [http://localhost:8025](http://localhost:8025).
4. Abre la dirección de la empresa en el navegador. En local, el servidor está configurado para HTTPS.

El alta crea la base independiente de la empresa, aplica sus migraciones, copia los datos de catálogo predeterminados y
prepara la cuenta Administrador. Si el alta falla, revisa que el rol de aprovisionamiento y su clave coincidan con
`.env`.

### Detener los servicios

```sh
vendor/bin/sail stop
```

Los datos de PostgreSQL y Redis permanecen en los volúmenes de Docker al detener los servicios.

## Pruebas

Con los contenedores en ejecución, ejecuta la suite PHP con:

```sh
vendor/bin/sail artisan test --compact
```

La configuración de Dusk y Selenium también está en `compose.yaml`.
