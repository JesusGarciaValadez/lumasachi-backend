<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

#[Signature('tenancy:migrate-central {--force : Force central migrations in production}')]
#[Description('Run only the central registry and Super Administrator migrations')]
final class MigrateCentralDatabase extends Command
{
    public function handle(): int
    {
        $connectionName = config('tenancy.database.central_connection');

        if (!is_string($connectionName) || $connectionName === '') {
            $this->error('The central database connection is not configured.');

            return self::FAILURE;
        }

        $connectionConfiguration = config("database.connections.{$connectionName}");

        if (!is_array($connectionConfiguration) || ($connectionConfiguration['driver'] ?? null) !== 'pgsql') {
            $this->error('The central database connection must use PostgreSQL.');

            return self::FAILURE;
        }

        if (DB::connection($connectionName)->getDriverName() !== 'pgsql') {
            $this->error('The resolved central database connection must use PostgreSQL.');

            return self::FAILURE;
        }

        $exitCode = Artisan::call('migrate', [
            '--database' => $connectionName,
            '--path' => [database_path('migrations/central')],
            '--realpath' => true,
            '--force' => (bool)$this->option('force'),
            '--no-interaction' => true,
        ]);

        $this->output->write(Artisan::output());

        return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
