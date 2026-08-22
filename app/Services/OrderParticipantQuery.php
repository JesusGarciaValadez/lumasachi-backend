<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class OrderParticipantQuery
{
    /**
     * Get active companies that a Super Administrator may select for intake.
     *
     * @return Collection<int, Company>
     */
    public function companiesFor(User $actor): Collection
    {
        if (!$actor->isSuperAdministrator()) {
            return new Collection;
        }

        return Company::query()
            ->select(['id', 'uuid', 'name'])
            ->active()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get active customers for the actor's effective company.
     *
     * @return Collection<int, User>
     */
    public function customersFor(User $actor, ?int $requestedCompanyId = null): Collection
    {
        return $this->participantsFor($actor, UserRole::CUSTOMER, $requestedCompanyId);
    }

    /**
     * @return Collection<int, User>
     */
    private function participantsFor(User $actor, UserRole $role, ?int $requestedCompanyId): Collection
    {
        $companyId = $this->effectiveCompanyId($actor, $requestedCompanyId);

        if ($companyId === null) {
            return new Collection;
        }

        return $this->participantQuery($companyId, $role)->get();
    }

    /**
     * Resolve the company used by a participant lookup or order creation.
     */
    public function effectiveCompanyId(User $actor, ?int $requestedCompanyId = null): ?int
    {
        $companyId = $actor->isSuperAdministrator() ? $requestedCompanyId : $actor->company_id;

        if ($companyId === null) {
            return null;
        }

        return Company::query()
            ->active()
            ->whereKey($companyId)
            ->value('id');
    }

    /**
     * Build the shared, redacted participant query.
     *
     * @return Builder<User>
     */
    public function participantQuery(int $companyId, UserRole $role): Builder
    {
        return User::query()
            ->select(['id', 'uuid', 'first_name', 'last_name'])
            ->where('company_id', $companyId)
            ->where('role', $role->value)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * Get active employees for the actor's effective company.
     *
     * @return Collection<int, User>
     */
    public function employeesFor(User $actor, ?int $requestedCompanyId = null): Collection
    {
        return $this->participantsFor($actor, UserRole::EMPLOYEE, $requestedCompanyId);
    }

    /**
     * Assert that the customer and employee belong to the same valid intake company.
     *
     * @param array<string, mixed> $payload
     *
     * @throws ValidationException
     */
    public function assertOrderParticipants(User $actor, array $payload): void
    {
        $errors = [];

        if (!$actor->isSuperAdministrator() && array_key_exists('company_id', $payload)) {
            $errors['company_id'] = [$this->invalidMessage('company_id')];
        }

        $requestedCompanyId = $actor->isSuperAdministrator()
            ? $this->toNullableInt($payload['company_id'] ?? null)
            : null;
        $companyId = $this->effectiveCompanyId($actor, $requestedCompanyId);

        if ($companyId === null) {
            if ($actor->isSuperAdministrator()) {
                $errors['company_id'] ??= [$this->requiredMessage('company_id')];
            }

            $errors['customer_id'] = [$this->invalidMessage('customer_id')];
            $errors['assigned_to'] = [$this->invalidMessage('assigned_to')];

            throw ValidationException::withMessages($errors);
        }

        $customerId = $this->toNullableInt($payload['customer_id'] ?? null);
        if ($customerId === null || !$this->participantQuery($companyId, UserRole::CUSTOMER)->whereKey($customerId)->exists()) {
            $errors['customer_id'] = [$this->invalidMessage('customer_id')];
        }

        $employeeId = $this->toNullableInt($payload['assigned_to'] ?? null);
        if ($employeeId === null || !$this->participantQuery($companyId, UserRole::EMPLOYEE)->whereKey($employeeId)->exists()) {
            $errors['assigned_to'] = [$this->invalidMessage('assigned_to')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function invalidMessage(string $attribute): string
    {
        return __('validation.custom.in', ['attribute' => __('validation.attributes.' . $attribute)]);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int)$value;
        }

        return null;
    }

    private function requiredMessage(string $attribute): string
    {
        return __('validation.custom.required', ['attribute' => __('validation.attributes.' . $attribute)]);
    }
}
