<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Confirm company access</title>
</head>
<body>
<main>
    <h1>Confirm company access</h1>
    <p>You are entering this company as a platform support administrator. This action is recorded.</p>
    <form method="post" action="{{ url('/central/entry') }}">
        @csrf
        <input id="token" type="hidden" name="token" value="">
        <button id="continue" type="submit" disabled>Continue</button>
    </form>
</main>
<script>
    const fragment = new URLSearchParams(window.location.hash.slice(1));
    const token = fragment.get('token');
    window.history.replaceState(null, '', window.location.pathname + window.location.search);

    if (token && /^[a-zA-Z0-9]{64}$/.test(token)) {
        document.getElementById('token').value = token;
        document.getElementById('continue').disabled = false;
    }
</script>
</body>
</html>
