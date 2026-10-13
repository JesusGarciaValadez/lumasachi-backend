<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Company onboarding</title>
</head>
<body>
<main>
    <p><a href="{{ route('central.dashboard', ['centralHost' => request()->route('centralHost')]) }}">Central
            administration</a></p>
    <h1>Company onboarding</h1>

    @foreach (['status', 'info', 'error'] as $messageType)
        @if (session($messageType))
            <p role="status">{{ session($messageType) }}</p>
        @endif
    @endforeach

    <section aria-labelledby="create-company-heading">
        <h2 id="create-company-heading">Create a company</h2>
        @if ($parentDomains === [])
            <p>Company onboarding is unavailable: configure a non-IP tenant parent domain first.</p>
        @else
            <form method="post"
                  action="{{ route('central.companies.store', ['centralHost' => request()->route('centralHost')]) }}">
                @csrf
                <div>
                    <label for="company_name">Company name</label>
                    <input id="company_name" name="company_name" value="{{ old('company_name') }}" maxlength="255"
                           required>
                    @error('company_name')<p>{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="subdomain">Company subdomain</label>
                    <input id="subdomain" name="subdomain" value="{{ old('subdomain') }}" maxlength="63"
                           pattern="[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?" required>
                    @error('subdomain')<p>{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="parent_domain">Tenant parent domain</label>
                    <select id="parent_domain" name="parent_domain" required>
                        @foreach ($parentDomains as $parentDomain)
                            <option
                                value="{{ $parentDomain }}" @selected(old('parent_domain') === $parentDomain)>{{ $parentDomain }}</option>
                        @endforeach
                    </select>
                    @error('parent_domain')<p>{{ $message }}</p>@enderror
                </div>
                <fieldset>
                    <legend>First Administrator</legend>
                    <div>
                        <label for="administrator_first_name">First name</label>
                        <input id="administrator_first_name" name="administrator_first_name"
                               value="{{ old('administrator_first_name') }}" maxlength="255" required>
                        @error('administrator_first_name')<p>{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="administrator_last_name">Last name</label>
                        <input id="administrator_last_name" name="administrator_last_name"
                               value="{{ old('administrator_last_name') }}" maxlength="255" required>
                        @error('administrator_last_name')<p>{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="administrator_email">Email</label>
                        <input id="administrator_email" type="email" name="administrator_email"
                               value="{{ old('administrator_email') }}" maxlength="255" required>
                        @error('administrator_email')<p>{{ $message }}</p>@enderror
                    </div>
                </fieldset>
                <button type="submit">Create and provision company</button>
            </form>
        @endif
    </section>

    <section aria-labelledby="companies-heading">
        <h2 id="companies-heading">Companies</h2>
        @if ($onboardings->isEmpty())
            <p>No companies have been onboarded yet.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th scope="col">Company</th>
                    <th scope="col">Tenant host</th>
                    <th scope="col">First Administrator</th>
                    <th scope="col">Status</th>
                    <th scope="col">Attempts</th>
                    <th scope="col">Last failure</th>
                    <th scope="col">Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($onboardings as $onboarding)
                    <tr>
                        <td>{{ $onboarding->company_name }}</td>
                        <td>{{ $onboarding->tenant_domain }}</td>
                        <td>{{ $onboarding->administrator_first_name }} {{ $onboarding->administrator_last_name }}
                            <br>{{ $onboarding->administrator_email }}</td>
                        <td>{{ $onboarding->provisioning_status }}</td>
                        <td>{{ $onboarding->attempt_count }}</td>
                        <td>
                            @if ($onboarding->last_failed_stage)
                                {{ $onboarding->last_failed_stage }} ({{ $onboarding->last_failure_type }})
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($onboarding->provisioning_status !== 'ready')
                                <form method="post"
                                      action="{{ route('central.companies.onboarding.retry', ['centralHost' => request()->route('centralHost'), 'company' => $onboarding->company_uuid]) }}">
                                    @csrf
                                    <button type="submit">Retry / resume</button>
                                </form>
                            @else
                                <form method="post"
                                      action="{{ route('central.companies.enter', ['centralHost' => request()->route('centralHost'), 'company' => $onboarding->company_id]) }}">
                                    @csrf
                                    <button type="submit" dusk="company-enter-{{ $onboarding->company_uuid }}">Enter
                                        company
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</main>
</body>
</html>
