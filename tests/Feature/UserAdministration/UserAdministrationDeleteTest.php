<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;

uses(RefreshDatabase::class);

test('super administrator can archive a user', function (): void {
    $superAdministrator = User::factory()->active()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);
    $target = User::factory()->active()->create([
        'role' => UserRole::EMPLOYEE->value,
    ]);

    $this->actingAs($superAdministrator)
        ->delete('/user/' . $target->uuid)
        ->assertRedirect('/users')
        ->assertSessionHas('success');

    $this->assertSoftDeleted($target);
    $this->assertModelExists(User::withTrashed()->findOrFail($target->id));
    $this->assertNull(User::query()->find($target->id));

    $this->actingAs($superAdministrator)
        ->get('/user/' . $target->uuid)
        ->assertNotFound();
});

test('only super administrators can archive users', function (): void {
    $target = User::factory()->active()->create([
        'role' => UserRole::EMPLOYEE->value,
    ]);

    foreach ([UserRole::ADMINISTRATOR, UserRole::EMPLOYEE, UserRole::CUSTOMER] as $role) {
        $actor = User::factory()->active()->create([
            'role' => $role->value,
        ]);

        $this->actingAs($actor)
            ->delete('/user/' . $target->uuid)
            ->assertForbidden();
    }

    $this->assertModelExists($target);
});

test('super administrators cannot archive themselves', function (): void {
    $superAdministrator = User::factory()->active()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);

    $this->actingAs($superAdministrator)
        ->delete('/user/' . $superAdministrator->uuid)
        ->assertForbidden();

    $this->assertModelExists($superAdministrator);
});

test('profile exposes delete capability only to super administrators', function (): void {
    $company = Company::factory()->active()->create();
    $superAdministrator = User::factory()->active()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);
    $administrator = User::factory()->active()->create([
        'company_id' => $company->id,
        'role' => UserRole::ADMINISTRATOR->value,
    ]);
    $target = User::factory()->active()->create([
        'company_id' => $company->id,
        'role' => UserRole::EMPLOYEE->value,
    ]);

    $this->actingAs($superAdministrator)
        ->get('/user/' . $target->uuid)
        ->assertInertia(fn(InertiaAssert $page) => $page
            ->where('capabilities.can_delete', true));

    $this->actingAs($administrator)
        ->get('/user/' . $target->uuid)
        ->assertInertia(fn(InertiaAssert $page) => $page
            ->where('capabilities.can_delete', false));
});

test('super administrator can archive a user referenced by orders', function (): void {
    $superAdministrator = User::factory()->active()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);
    $target = User::factory()->active()->create([
        'role' => UserRole::EMPLOYEE->value,
    ]);
    $order = Order::factory()->create([
        'assigned_to' => $target->id,
        'created_by' => $superAdministrator->id,
        'updated_by' => $superAdministrator->id,
    ]);

    $this->actingAs($superAdministrator)
        ->delete('/user/' . $target->uuid)
        ->assertRedirect('/users')
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($target);
    $this->assertModelExists(User::withTrashed()->findOrFail($target->id));
    $this->assertModelExists($order);
    $this->assertSame($target->id, $order->refresh()->assigned_to);
});
