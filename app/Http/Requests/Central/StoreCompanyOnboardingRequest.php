<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\CentralSuperAdministrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class StoreCompanyOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $administrator = $this->user('central');

        return $administrator instanceof CentralSuperAdministrator && $administrator->is_active;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'max:63', 'regex:/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/D'],
            'parent_domain' => ['required', 'string', Rule::in($this->tenantParentDomains())],
            'administrator_first_name' => ['required', 'string', 'max:255'],
            'administrator_last_name' => ['required', 'string', 'max:255'],
            'administrator_email' => ['required', 'string', 'email', 'max:255'],
            'subdomain_domain' => ['prohibited'],
            'administrator_uuid' => ['prohibited'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tenantParentDomains(): array
    {
        $domains = config('tenancy.central_domains', []);

        return is_array($domains)
            ? array_values(array_unique(array_filter(
                array_map(static fn(mixed $domain): string => is_string($domain) ? mb_strtolower(mb_trim($domain)) : '', $domains),
                static fn(string $domain): bool => $domain !== '' && filter_var($domain, FILTER_VALIDATE_IP) === false,
            )))
            : [];
    }

    /**
     * @return array{
     *     company_name: string,
     *     subdomain: string,
     *     parent_domain: string,
     *     administrator_first_name: string,
     *     administrator_last_name: string,
     *     administrator_email: string
     * }
     */
    public function validatedOnboardingData(): array
    {
        $validated = $this->validated();

        return [
            'company_name' => $this->validatedString($validated, 'company_name'),
            'subdomain' => $this->validatedString($validated, 'subdomain'),
            'parent_domain' => $this->validatedString($validated, 'parent_domain'),
            'administrator_first_name' => $this->validatedString($validated, 'administrator_first_name'),
            'administrator_last_name' => $this->validatedString($validated, 'administrator_last_name'),
            'administrator_email' => $this->validatedString($validated, 'administrator_email'),
        ];
    }

    /** @param array<string, mixed> $validated */
    private function validatedString(array $validated, string $key): string
    {
        $value = $validated[$key] ?? null;

        if (!is_string($value)) {
            throw new LogicException("The validated onboarding field {$key} must be a string.");
        }

        return $value;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || !is_string($this->input('subdomain')) || !is_string($this->input('parent_domain'))) {
                return;
            }

            $domain = $this->input('subdomain') . '.' . $this->input('parent_domain');
            $centralHosts = config('tenancy.central_hosts', []);
            $centralHosts = is_array($centralHosts) ? array_map('mb_strtolower', array_filter($centralHosts, 'is_string')) : [];

            if (in_array($domain, $centralHosts, true)) {
                $validator->errors()->add('subdomain', 'This subdomain conflicts with a central application host.');

                return;
            }

            $centralConnection = (string)config('tenancy.database.central_connection', config('database.default'));
            $domainExists = DB::connection($centralConnection)
                ->table('domains')
                ->whereRaw('LOWER(domain) = ?', [$domain])
                ->exists();

            if ($domainExists) {
                $validator->errors()->add('subdomain', 'This company subdomain is already in use.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => is_string($this->input('subdomain')) ? mb_strtolower(mb_trim($this->input('subdomain'))) : $this->input('subdomain'),
            'parent_domain' => is_string($this->input('parent_domain')) ? mb_strtolower(mb_trim($this->input('parent_domain'))) : $this->input('parent_domain'),
            'administrator_email' => is_string($this->input('administrator_email')) ? mb_strtolower(mb_trim($this->input('administrator_email'))) : $this->input('administrator_email'),
            'company_name' => is_string($this->input('company_name')) ? mb_trim($this->input('company_name')) : $this->input('company_name'),
            'administrator_first_name' => is_string($this->input('administrator_first_name')) ? mb_trim($this->input('administrator_first_name')) : $this->input('administrator_first_name'),
            'administrator_last_name' => is_string($this->input('administrator_last_name')) ? mb_trim($this->input('administrator_last_name')) : $this->input('administrator_last_name'),
        ]);
    }
}
