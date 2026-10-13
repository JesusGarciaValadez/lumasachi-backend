<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\User;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

test('tenant-owned models reject the default central connection without company context', function (): void {
    config(['tenancy.allow_legacy_model_connection' => false]);

    foreach ([Attachment::class, CompanySetting::class, Order::class, User::class] as $modelClass) {
        expect(fn(): ?string => (new $modelClass())->getConnectionName())
            ->toThrow(TenancyNotInitializedException::class);
    }
});

test('the old default connection remains available only to the legacy test harness', function (): void {
    config(['tenancy.allow_legacy_model_connection' => true]);

    expect((new Order())->getConnectionName())->toBeNull();

    config(['tenancy.allow_legacy_model_connection' => false]);

    expect(fn(): ?string => (new Order())->getConnectionName())
        ->toThrow(TenancyNotInitializedException::class);
});
