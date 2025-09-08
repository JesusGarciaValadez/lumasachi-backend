<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Models\User;

final class UsersController extends Controller
{
    /**
     * Get employees of the authenticated user's company.
     */
    public function employees(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $query = User::query();

        if (is_null($companyId)) {
            // Same company means company_id is also null
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $companyId);
        }

        // Optional: exclude soft-deleted or inactive if needed; not specified in requirement
        $users = $query->with('company')->get();

        return response()->json(UserResource::collection($users));
    }

    /**
     * Get customers: users from a different company than the authenticated user's company.
     */
    public function customers(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $query = User::query();

        if (is_null($companyId)) {
            // Different than null => company_id is not null
            $query->whereNotNull('company_id');
        } else {
            // Different company or null
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', '!=', $companyId)
                  ->orWhereNull('company_id');
            });
        }

        $users = $query->with('company')->get();

        return response()->json(UserResource::collection($users));
    }
}

