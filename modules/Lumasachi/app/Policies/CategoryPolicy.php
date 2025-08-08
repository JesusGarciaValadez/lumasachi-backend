<?php

namespace Modules\Lumasachi\app\Policies;

use App\Models\User;
use Modules\Lumasachi\app\Models\Category;
use Illuminate\Auth\Access\Response;
use Modules\Lumasachi\app\Enums\UserRole;

class CategoryPolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        if ($user->role === UserRole::CUSTOMER) {
            return false;
        }

        if ($user->company_id) {
            $creator = User::find($category->created_by);
            return $creator && $user->company_id === $creator->company_id;
        }

        return false;
    }
}
