<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use RuntimeException;
use stdClass;

final readonly class TenantOnboardingRecord
{
    public function __construct(
        public string  $tenantDomain,
        public string  $administratorFirstName,
        public string  $administratorLastName,
        public string  $administratorEmail,
        public string  $administratorUuid,
        public int     $attemptCount,
        public ?string $provisioningLockToken,
        public ?string $provisioningLockedAt,
        public ?string $databaseCreationStartedAt,
        public ?string $databaseCreatedAt,
        public ?string $migrationsCompletedAt,
        public ?string $defaultsSeededAt,
        public ?string $companySettingsCreatedAt,
        public ?string $administratorCreatedAt,
        public ?string $invitationQueuedAt,
    )
    {
    }

    public static function fromDatabaseRow(stdClass $row): self
    {
        return new self(
            tenantDomain: self::stringColumn($row, 'tenant_domain'),
            administratorFirstName: self::stringColumn($row, 'administrator_first_name'),
            administratorLastName: self::stringColumn($row, 'administrator_last_name'),
            administratorEmail: self::stringColumn($row, 'administrator_email'),
            administratorUuid: self::stringColumn($row, 'administrator_uuid'),
            attemptCount: self::integerColumn($row, 'attempt_count'),
            provisioningLockToken: self::nullableStringColumn($row, 'provisioning_lock_token'),
            provisioningLockedAt: self::nullableStringColumn($row, 'provisioning_locked_at'),
            databaseCreationStartedAt: self::nullableStringColumn($row, 'database_creation_started_at'),
            databaseCreatedAt: self::nullableStringColumn($row, 'database_created_at'),
            migrationsCompletedAt: self::nullableStringColumn($row, 'migrations_completed_at'),
            defaultsSeededAt: self::nullableStringColumn($row, 'defaults_seeded_at'),
            companySettingsCreatedAt: self::nullableStringColumn($row, 'company_settings_created_at'),
            administratorCreatedAt: self::nullableStringColumn($row, 'administrator_created_at'),
            invitationQueuedAt: self::nullableStringColumn($row, 'invitation_queued_at'),
        );
    }

    private static function stringColumn(stdClass $row, string $column): string
    {
        $value = $row->{$column} ?? null;

        if (!is_string($value)) {
            throw new RuntimeException("Tenant onboarding column {$column} must be a string.");
        }

        return $value;
    }

    private static function integerColumn(stdClass $row, string $column): int
    {
        $value = $row->{$column} ?? null;

        if (!is_int($value) && (!is_string($value) || !ctype_digit($value))) {
            throw new RuntimeException("Tenant onboarding column {$column} must be an integer.");
        }

        return (int)$value;
    }

    private static function nullableStringColumn(stdClass $row, string $column): ?string
    {
        $value = $row->{$column} ?? null;

        if ($value !== null && !is_string($value)) {
            throw new RuntimeException("Tenant onboarding column {$column} must be a string or null.");
        }

        return $value;
    }
}
