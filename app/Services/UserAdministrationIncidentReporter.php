<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\UserAdministrationException;
use App\Models\User;
use App\Notifications\UserAdministrationIncidentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class UserAdministrationIncidentReporter
{
    /** @var list<string> */
    private const OPERATIONS = ['create', 'update', 'delete'];

    /**
     * Capture an unexpected administration failure without retaining request data.
     */
    public function capture(Throwable $exception, Request $request, string $operation): UserAdministrationException
    {
        $actor = $request->user();
        $incidentId = Str::uuid7()->toString();
        $operation = in_array($operation, self::OPERATIONS, true) ? $operation : 'unknown';
        $context = [
            'incident_id' => $incidentId,
            'operation' => $operation,
            'actor_role' => $actor instanceof User ? $actor->role?->value : null,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'exception_class' => $exception::class,
        ];
        $incident = new UserAdministrationException($incidentId, $context);

        report($incident);

        $notification = new UserAdministrationIncidentNotification($context);

        User::query()
            ->where('role', UserRole::SUPER_ADMINISTRATOR->value)
            ->where('is_active', true)
            ->get()
            ->each(function (User $recipient) use ($notification): void {
                $recipient->notify($notification);
            });

        return $incident;
    }
}
