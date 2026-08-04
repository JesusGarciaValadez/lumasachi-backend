<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UserService
{
    /**
     * Create a user from already authorized and validated administration data.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): User
    {
        $user = DB::transaction(function () use ($attributes): User {
            $attributes = Arr::only($attributes, [
                'first_name',
                'last_name',
                'email',
                'password',
                'company_id',
                'role',
                'phone_number',
                'is_active',
                'notes',
                'type',
                'locale',
            ]);
            $attributes['uuid'] ??= Str::uuid7()->toString();
            $attributes['activated_at'] = (bool)($attributes['is_active'] ?? false) ? now() : null;
            $attributes['email_verified_at'] = null;
            $attributes['must_change_password'] = true;

            return User::query()->create($attributes);
        });

        if ($user->is_active) {
            event(new Registered($user));
        }

        return $user;
    }

    /**
     * Update a user from already authorized and validated administration data.
     *
     * @param array<string, mixed> $attributes
     *
     * @throws ValidationException
     */
    public function update(User $user, array $attributes): User
    {
        $sendVerificationNotification = false;

        $updatedUser = DB::transaction(function () use ($user, $attributes, &$sendVerificationNotification): User {
            $user->refresh();
            $wasActive = $user->is_active;
            $attributes = Arr::only($attributes, [
                'first_name',
                'last_name',
                'email',
                'password',
                'company_id',
                'role',
                'phone_number',
                'is_active',
                'notes',
                'type',
                'locale',
            ]);

            if (array_key_exists('password', $attributes) && blank($attributes['password'])) {
                unset($attributes['password']);
            }

            $nextRole = $attributes['role'] ?? $user->role;
            $nextRole = $nextRole instanceof UserRole ? $nextRole : UserRole::tryFrom((string)$nextRole);
            $nextIsActive = array_key_exists('is_active', $attributes)
                ? (bool)$attributes['is_active']
                : $user->is_active;

            if (
                $this->isFinalActiveSuperAdministrator($user)
                && ($nextRole !== UserRole::SUPER_ADMINISTRATOR || !$nextIsActive)
            ) {
                throw ValidationException::withMessages([
                    'role' => __('users.validation.final_super_administrator'),
                ]);
            }

            if ($nextIsActive && !$user->is_active && $user->activated_at === null) {
                $attributes['activated_at'] = now();
            }

            $user->fill($attributes);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
                $sendVerificationNotification = $nextIsActive;
            }

            if ($nextIsActive && !$wasActive && $user->email_verified_at === null) {
                $sendVerificationNotification = true;
            }

            $user->save();

            return $user->refresh();
        });

        if ($sendVerificationNotification) {
            $updatedUser->sendEmailVerificationNotification();
        }

        return $updatedUser;
    }

    /**
     * Soft-delete a user after the caller has authorized the operation.
     *
     * @throws ValidationException
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->refresh();

            if ($this->isFinalActiveSuperAdministrator($user)) {
                throw ValidationException::withMessages([
                    'delete' => __('users.validation.final_super_administrator_delete'),
                ]);
            }

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }

    private function isFinalActiveSuperAdministrator(User $user): bool
    {
        return $user->role === UserRole::SUPER_ADMINISTRATOR
            && $user->is_active
            && User::query()
                ->where('role', UserRole::SUPER_ADMINISTRATOR->value)
                ->where('is_active', true)
                ->count() <= 1;
    }
}
