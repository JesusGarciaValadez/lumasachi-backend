<?php

declare(strict_types=1);

namespace Tests\Unit\UserAdministration;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Http\Requests\UserAdministration\StoreUserRequest;
use App\Http\Requests\UserAdministration\UpdateUserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UserAdministrationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_request_rejects_model_managed_fields(): void
    {
        $actor = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        $request = StoreUserRequest::create('/user', 'POST', [
            ...$this->baseAttributes(),
            'uuid' => '11111111-1111-7111-8111-111111111111',
        ]);
        $request->setUserResolver(fn(): User => $actor);

        $exception = $this->validateAndCatch($request);

        $this->assertArrayHasKey('uuid', $exception->errors());
    }

    public function test_administrator_update_request_rejects_forbidden_fields(): void
    {
        $company = Company::factory()->active()->create();
        $actor = User::factory()->active()->create([
            'company_id' => $company->id,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);
        $target = User::factory()->active()->create([
            'company_id' => $company->id,
            'role' => UserRole::EMPLOYEE->value,
        ]);
        $request = UpdateUserRequest::create('/user/' . $target->uuid, 'PUT', [
            ...$this->baseAttributes(),
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => false,
            'company_id' => Company::factory()->create()->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $request->setUserResolver(fn(): User => $actor);
        $request->setRouteResolver(fn(): Route => $this->routeFor($target));

        $exception = $this->validateAndCatch($request);

        $this->assertArrayHasKey('is_active', $exception->errors());
        $this->assertArrayHasKey('company_id', $exception->errors());
        $this->assertArrayHasKey('password', $exception->errors());
    }

    public function test_super_administrator_update_request_accepts_a_blank_optional_password(): void
    {
        $actor = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        $target = User::factory()->active()->create();
        $request = UpdateUserRequest::create('/user/' . $target->uuid, 'PUT', [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'role' => $target->role->value,
            'is_active' => true,
            'type' => $target->type->value,
            'locale' => Locale::ENGLISH->value,
            'password' => '',
            'password_confirmation' => '',
        ]);
        $request->setUserResolver(fn(): User => $actor);
        $request->setRouteResolver(fn(): Route => $this->routeFor($target));
        $request->setContainer($this->app);

        $validator = $this->makeValidator($request);

        $this->assertFalse($validator->fails(), implode('; ', $validator->errors()->all()));

        $this->assertSame('', $request->input('password'));
    }

    /**
     * @return array<string, mixed>
     */
    private function baseAttributes(): array
    {
        return [
            'first_name' => 'Request',
            'last_name' => 'User',
            'email' => 'request-user@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_id' => null,
            'role' => UserRole::EMPLOYEE->value,
            'phone_number' => null,
            'is_active' => true,
            'notes' => null,
            'type' => UserType::INDIVIDUAL->value,
            'locale' => Locale::ENGLISH->value,
        ];
    }

    private function routeFor(User $target): Route
    {
        $route = new Route(['PUT'], '/user/{user}', static fn(): null => null);
        $route->bind(Request::create('/user/' . $target->uuid, 'PUT'));
        $route->setParameter('user', $target);

        return $route;
    }

    private function validateAndCatch(StoreUserRequest|UpdateUserRequest $request): ValidationException
    {
        $validator = $this->makeValidator($request);

        if ($validator->fails()) {
            return ValidationException::withMessages($validator->errors()->toArray());
        }

        $this->fail('The request unexpectedly passed validation.');
    }

    private function makeValidator(StoreUserRequest|UpdateUserRequest $request): \Illuminate\Validation\Validator
    {
        $validator = $this->app['validator']->make($request->all(), $request->rules(), $request->messages());
        $validator->after($request->after());

        return $validator;
    }
}
