<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('orders and public attachments are available only through their tenant host', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $defaultConnectionName = (string)config('database.default');
    $centralConnectionName = 'tenant_order_attachment_central_' . bin2hex(random_bytes(5));
    $tenantConnectionNames = [
        'alpha' => 'tenant_order_attachment_alpha_' . bin2hex(random_bytes(4)),
        'beta' => 'tenant_order_attachment_beta_' . bin2hex(random_bytes(4)),
    ];
    $provisionerConnectionName = (string)config('tenancy.database.provisioner_connection');
    $temporaryTenantStorageRoot = sys_get_temp_dir() . '/lumasachi-tenant-order-attachment-' . bin2hex(random_bytes(8));
    $attachmentFilePath = 'tenant-host-isolation/' . Str::uuid7() . '.pdf';
    $configurationKeys = [
        'database.default',
        'database.connections.' . $defaultConnectionName,
        'database.connections.tenant',
        'database.connections.' . $centralConnectionName,
        'database.connections.' . $tenantConnectionNames['alpha'],
        'database.connections.' . $tenantConnectionNames['beta'],
        'database.connections.' . $provisionerConnectionName,
        'tenancy.database.central_connection',
        'tenancy.database.template_tenant_connection',
        'tenancy.central_domains',
        'tenancy.central_hosts',
        'tenancy.allow_legacy_model_connection',
        'tenancy.allow_legacy_test_routes',
        'tenancy.filesystem.root_override.tenant-private',
    ];
    $originalConfiguration = [];

    foreach ($configurationKeys as $configurationKey) {
        $originalConfiguration[$configurationKey] = config($configurationKey);
    }

    $tenants = [];
    $tenantFixtures = [];

    try {
        $testDatabaseConfiguration = $originalConfiguration['database.connections.' . $defaultConnectionName];

        if (!is_array($testDatabaseConfiguration) || ($testDatabaseConfiguration['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('Tenant host isolation requires the configured PostgreSQL test connection.');
        }

        unset($testDatabaseConfiguration['url']);
        $testDatabaseConfiguration['database'] = 'testing';
        config(['database.connections.' . $defaultConnectionName => $testDatabaseConfiguration]);
        DB::purge($defaultConnectionName);
        DB::setDefaultConnection($defaultConnectionName);

        expect(DB::connection()->selectOne('select current_database() as database_name')?->database_name)
            ->toBe('testing');

        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        [$centralDatabaseName] = $disposableDatabases->createPair();
        $centralConfiguration = (new ConfigurationUrlParser)->parseConfiguration(DB::connection()->getConfig());
        unset($centralConfiguration['url']);
        $centralConfiguration['database'] = $centralDatabaseName;
        $centralConfiguration['name'] = $centralConnectionName;

        config([
            'database.connections.' . $centralConnectionName => $centralConfiguration,
            'tenancy.database.central_connection' => $centralConnectionName,
            'tenancy.database.template_tenant_connection' => $defaultConnectionName,
            'tenancy.central_domains' => ['example.test'],
            'tenancy.central_hosts' => ['app.example.test'],
            'tenancy.allow_legacy_model_connection' => false,
            'tenancy.allow_legacy_test_routes' => false,
            'tenancy.filesystem.root_override.tenant-private' => $temporaryTenantStorageRoot . '/tenant-%tenant%',
        ]);
        DB::purge($centralConnectionName);
        Storage::forgetDisk('tenant-private');

        if (Artisan::call('tenancy:migrate-central', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('The tenant host isolation central migrations failed: ' . Artisan::output());
        }

        DB::setDefaultConnection($defaultConnectionName);

        foreach ($tenantConnectionNames as $subdomain => $connectionName) {
            $company = Company::query()->create(['name' => ucfirst($subdomain) . ' Host Isolation']);
            $tenant = Tenant::factory()->make(['id' => $company->uuid]);
            $tenant->save();
            $tenant->domains()->create(['domain' => $subdomain . '.example.test']);
            $tenant->database()->makeCredentials();
            $disposableDatabases->registerTenantProvisioningAttempt($tenant);
            $tenant->database()->manager()->createDatabase($tenant);
            $disposableDatabases->registerProvisionedTenant($tenant);

            config(['database.connections.' . $connectionName => $tenant->database()->connection()]);

            if (Artisan::call('migrate', [
                    '--database' => $connectionName,
                    '--force' => true,
                    '--no-interaction' => true,
                    '--path' => [database_path('migrations/tenant')],
                    '--realpath' => true,
                ]) !== 0) {
                throw new RuntimeException('The ' . $subdomain . ' tenant migrations failed: ' . Artisan::output());
            }

            $tenant->forceFill([
                'provisioning_status' => 'ready',
                'ready_at' => now(),
            ])->save();
            $tenants[$subdomain] = $tenant;
        }

        foreach ($tenants as $subdomain => $tenant) {
            $tenant->run(static function () use (&$tenantFixtures, $subdomain, $attachmentFilePath): void {
                $administrator = User::factory()->create([
                    'role' => UserRole::ADMINISTRATOR->value,
                    'email' => $subdomain . '-administrator@example.test',
                ]);

                $tenantFixtures[$subdomain] = [
                    'token' => $administrator->createToken($subdomain . ' host isolation test')->plainTextToken,
                ];

                if ($subdomain !== 'alpha') {
                    return;
                }

                $customer = User::factory()->create([
                    'role' => UserRole::CUSTOMER->value,
                    'email' => 'alpha-customer@example.test',
                ]);
                $order = Order::factory()->createQuietly([
                    'title' => 'Alpha-only host isolation order',
                    'customer_id' => $customer->id,
                    'assigned_to' => $administrator->id,
                    'created_by' => $administrator->id,
                    'updated_by' => $administrator->id,
                ]);
                $attachmentContents = '%PDF-1.4 Alpha tenant inspection';
                $attachment = $order->attachments()->create([
                    'uuid' => (string)Str::uuid7(),
                    'file_name' => 'alpha-inspection.pdf',
                    'file_path' => $attachmentFilePath,
                    'file_size' => mb_strlen($attachmentContents),
                    'mime_type' => 'application/pdf',
                    'uploaded_by' => $administrator->id,
                ]);

                expect(Storage::disk('tenant-private')->put($attachmentFilePath, $attachmentContents))->toBeTrue();

                $tenantFixtures[$subdomain] += [
                    'order_uuid' => $order->uuid,
                    'attachment_uuid' => $attachment->uuid,
                    'created_date' => $order->created_at->toDateString(),
                ];
            });
        }

        $alphaFixture = $tenantFixtures['alpha'];
        $betaFixture = $tenantFixtures['beta'];
        $orderUuid = $alphaFixture['order_uuid'];
        $attachmentUuid = $alphaFixture['attachment_uuid'];
        $createdDate = $alphaFixture['created_date'];

        $this->withToken($alphaFixture['token'])
            ->getJson('http://alpha.example.test/api/v1/orders/' . $orderUuid)
            ->assertSuccessful()
            ->assertJsonPath('uuid', $orderUuid)
            ->assertJsonPath('title', 'Alpha-only host isolation order');

        $this->withToken($alphaFixture['token'])
            ->getJson('http://alpha.example.test/api/v1/orders/' . $orderUuid . '/attachments')
            ->assertSuccessful()
            ->assertJsonPath('attachments.0.uuid', $attachmentUuid)
            ->assertJsonPath('attachments.0.file_name', 'alpha-inspection.pdf');

        $this->withToken($betaFixture['token'])
            ->getJson('http://beta.example.test/api/v1/orders/' . $orderUuid)
            ->assertNotFound();

        $this->withToken($betaFixture['token'])
            ->getJson('http://beta.example.test/api/v1/orders/' . $orderUuid . '/attachments')
            ->assertNotFound();

        $authenticatedDownloadPath = '/api/v1/attachments/' . $attachmentUuid . '/download';
        $authenticatedPreviewPath = '/api/v1/attachments/' . $attachmentUuid . '/preview';

        $this->withToken($alphaFixture['token'])
            ->get('http://alpha.example.test' . $authenticatedDownloadPath)
            ->assertSuccessful()
            ->assertHeader('Content-Disposition', 'attachment; filename=alpha-inspection.pdf');

        $this->withToken($alphaFixture['token'])
            ->get('http://alpha.example.test' . $authenticatedPreviewPath)
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->withToken($betaFixture['token'])
            ->get('http://beta.example.test' . $authenticatedDownloadPath)
            ->assertNotFound();

        $this->withToken($betaFixture['token'])
            ->get('http://beta.example.test' . $authenticatedPreviewPath)
            ->assertNotFound();

        $trackingResponse = $this->postJson('http://alpha.example.test/api/v1/orders/track', [
            'uuid' => $orderUuid,
            'created_date' => $createdDate,
        ]);
        $trackingResponse->assertSuccessful()
            ->assertJsonPath('order.uuid', $orderUuid)
            ->assertJsonPath('order.title', 'Alpha-only host isolation order')
            ->assertJsonPath('order.attachments.0.uuid', $attachmentUuid)
            ->assertJsonPath('order.attachments.0.file_name', 'alpha-inspection.pdf');

        $downloadUrl = $trackingResponse->json('order.attachments.0.download_url');
        $previewUrl = $trackingResponse->json('order.attachments.0.preview_url');

        expect($downloadUrl)->toBeString()
            ->and($previewUrl)->toBeString()
            ->and(parse_url($downloadUrl, PHP_URL_HOST))->toBe('alpha.example.test')
            ->and(parse_url($previewUrl, PHP_URL_HOST))->toBe('alpha.example.test');

        $this->get($downloadUrl)
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=alpha-inspection.pdf');

        $this->get($previewUrl)
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->postJson('http://beta.example.test/api/v1/orders/track', [
            'uuid' => $orderUuid,
            'created_date' => $createdDate,
        ])->assertNotFound()
            ->assertJsonPath('code', 'orders.track_not_found');

        $this->get(str_replace('alpha.example.test', 'beta.example.test', $downloadUrl))->assertNotFound();
        $this->get(str_replace('alpha.example.test', 'beta.example.test', $previewUrl))->assertNotFound();
    } finally {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($tenantConnectionNames as $connectionName) {
            DB::purge($connectionName);
        }

        DB::purge('tenant');
        DB::purge($centralConnectionName);
        DB::setDefaultConnection($defaultConnectionName);

        try {
            $disposableDatabases->cleanup();
        } finally {
            config($originalConfiguration);
            DB::purge($centralConnectionName);
            foreach ($tenantConnectionNames as $connectionName) {
                DB::purge($connectionName);
            }
            DB::purge('tenant');
            DB::setDefaultConnection($defaultConnectionName);
            Storage::forgetDisk('tenant-private');
            File::deleteDirectory($temporaryTenantStorageRoot);
        }
    }
});
