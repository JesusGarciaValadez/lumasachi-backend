<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OrderParticipantLookupRequest;
use App\Http\Resources\OrderParticipantResource;
use App\Models\Company;
use App\Models\User;
use App\Services\OrderParticipantQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UsersController extends Controller
{
    /**
     * Get active companies available to a Super Administrator.
     */
    public function companies(OrderParticipantLookupRequest $request, OrderParticipantQuery $participantQuery): JsonResponse
    {
        $actor = $this->authenticatedUser($request);

        return response()->json($participantQuery->companiesFor($actor)->map(
            static fn (Company $company): array => [
                'id' => $company->id,
                'uuid' => $company->uuid,
                'name' => $company->name,
            ]
        )->values());
    }

    /**
     * Get employees of the authenticated user's effective order company.
     */
    public function employees(OrderParticipantLookupRequest $request, OrderParticipantQuery $participantQuery): JsonResponse
    {
        $actor = $this->authenticatedUser($request);

        return response()->json(OrderParticipantResource::collection(
            $participantQuery->employeesFor($actor, $request->companyId())
        ));
    }

    /**
     * Get customers of the authenticated user's effective order company.
     */
    public function customers(OrderParticipantLookupRequest $request, OrderParticipantQuery $participantQuery): JsonResponse
    {
        $actor = $this->authenticatedUser($request);

        return response()->json(OrderParticipantResource::collection(
            $participantQuery->customersFor($actor, $request->companyId())
        ));
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
