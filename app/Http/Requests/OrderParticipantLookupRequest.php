<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OrderParticipantLookupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if (!$actor instanceof User || !$actor->can('create', Order::class)) {
            return false;
        }

        return !$this->routeIs('api.users.companies') || $actor->isSuperAdministrator();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actor = $this->user();

        return [
            'company_id' => $actor instanceof User && $actor->isSuperAdministrator()
                ? ['nullable', 'integer', Rule::exists(Company::class, 'id')->where('is_active', true)]
                : ['prohibited'],
        ];
    }

    public function companyId(): ?int
    {
        $companyId = $this->validated('company_id');

        return $companyId === null ? null : (int)$companyId;
    }
}
