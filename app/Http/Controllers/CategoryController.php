<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoriesRequest;
use App\Traits\CachesCategories;
use Carbon\Carbon;

final class CategoryController extends Controller
{
    use CachesCategories;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $key = self::indexKeyForCompany($user->company_id);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlIndex()), function () use ($user) {
            $categories = Category::forCompany($user->company_id)
                ->active()
                ->orderBy('name', 'asc')
                ->orderBy('description', 'asc')
                ->get();

            // Return plain arrays to store in cache
            return $categories->toArray();
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreCategoriesRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeBulk(StoreCategoriesRequest $request): JsonResponse
    {
        $categoriesData = $request->validated()['categories'];
        $userId = Auth::id();
        $now = Carbon::now();

        $categoriesToInsert = array_map(function ($category) use ($userId, $now) {
            return [
                'uuid' => Str::uuid7()->toString(),
                'name' => $category['name'],
                'description' => $category['description'] ?? null,
                'is_active' => $category['is_active'] ?? true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $categoriesData);

        Category::insert($categoriesToInsert);

        // Bulk insert does not fire Eloquent events; manually bump cache version
        self::bumpVersion();

        return response()->json(['message' => 'Categories created successfully.'], 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.'], 200);
    }
}

