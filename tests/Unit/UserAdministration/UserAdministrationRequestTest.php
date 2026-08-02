<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Http\Requests\UserAdministration\StoreUserRequest;
use App\Http\Requests\UserAdministration\UpdateUserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

uses(RefreshDatabase::class);

test('store request rejects model managed fields', function (): void {
    $actor = User::factory()->active()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);
    $request = StoreUserRequest::create('/user', 'POST', [
        ...userAdministrationRequestBaseAttributes(),
        'uuid' => '11111111-1111-7111-8111-111111111111',
    ]);
    $request->setUserResolver(fn(): User => $actor);

    $exception = userAdministrationRequestValidationException($request);

    $this->assertArrayHasKey('uuid', $exception->errors());
});

test('administrator update request rejects forbidden fields', function (): void {
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
        ...userAdministrationRequestBaseAttributes(),
        'role' => UserRole::EMPLOYEE->value,
        'is_active' => false,
        'company_id' => Company::factory()->create()->id,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);
    $request->setUserResolver(fn(): User => $actor);
    $request->setRouteResolver(fn(): Route => userAdministrationRequestRouteFor($target));

    $exception = userAdministrationRequestValidationException($request);

    $this->assertArrayHasKey('is_active', $exception->errors());
    $this->assertArrayHasKey('company_id', $exception->errors());
    $this->assertArrayHasKey('password', $exception->errors());
});

test('super administrator update request accepts a blank optional password', function (): void {
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
    $request->setRouteResolver(fn(): Route => userAdministrationRequestRouteFor($target));
    $request->setContainer(app());

    $validator = userAdministrationRequestMakeValidator($request);

    $this->assertFalse($validator->fails(), implode('; ', $validator->errors()->all()));

    $this->assertSame('', $request->input('password'));
});

/**
 * @return array<string, mixed>
 */
function userAdministrationRequestBaseAttributes(): array
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

function userAdministrationRequestRouteFor(User $target): Route
{
    $route = new Route(['PUT'], '/user/{user}', static fn(): null => null);
    $route->bind(Request::create('/user/' . $target->uuid, 'PUT'));
    $route->setParameter('user', $target);

    return $route;
}

function userAdministrationRequestValidationException(StoreUserRequest|UpdateUserRequest $request): ValidationException
{
    $validator = userAdministrationRequestMakeValidator($request);

    if ($validator->fails()) {
        return ValidationException::withMessages($validator->errors()->toArray());
    }

    throw new LogicException('The request unexpectedly passed validation.');
}

function userAdministrationRequestMakeValidator(StoreUserRequest|UpdateUserRequest $request): Validator
{
    $validator = app(ValidatorFactory::class)->make($request->all(), $request->rules(), $request->messages());
    $validator->after($request->after());

    return $validator;
}
