<?php

declare(strict_types=1);

namespace Tests\Feature\UserAdministration;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use Tests\TestCase;

final class UserAdministrationDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_archive_a_user(): void
    {
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
    }

    public function test_only_super_administrators_can_archive_users(): void
    {
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
    }

    public function test_super_administrators_cannot_archive_themselves(): void
    {
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);

        $this->actingAs($superAdministrator)
            ->delete('/user/' . $superAdministrator->uuid)
            ->assertForbidden();

        $this->assertModelExists($superAdministrator);
    }

    public function test_profile_exposes_delete_capability_only_to_super_administrators(): void
    {
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
    }

    public function test_super_administrator_can_archive_a_user_referenced_by_orders(): void
    {
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
    }
}
