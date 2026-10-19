<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Golden Tor Admin – első belépés</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: #141c17; color: #f8f3ee; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 440px; background: #1b251e; border: 1px solid #2f4136; border-radius: 14px; padding: 32px; box-shadow: 0 30px 80px rgba(0,0,0,.35); }
        img { height: 44px; display: block; margin-bottom: 20px; }
        h1 { margin: 0 0 6px; font-size: 1.45rem; }
        p { margin: 0 0 22px; color: #b9c4bc; }
        label { display: block; margin: 0 0 14px; font-size: .85rem; font-weight: 600; color: #dfe6e1; }
        input { width: 100%; margin-top: 6px; padding: 11px 12px; border-radius: 8px; border: 1px solid #3c5145; background: #121a15; color: #fff; font: inherit; }
        input:focus { outline: 2px solid #b8962e; border-color: transparent; }
        button { width: 100%; margin-top: 8px; padding: 12px; border: 0; border-radius: 8px; background: #b8962e; color: #141c17; font: inherit; font-weight: 700; cursor: pointer; }
        .error { margin: -8px 0 14px; color: #fda4af; font-size: .82rem; }
        small { display: block; margin-top: 4px; color: #8fa096; font-weight: 400; }
    </style>
</head>
<body>
<main>
    <img src="{{ rtrim(config('goldentor.public_url'), '/') }}/assets/goldentor_logo_gold.png" alt="Golden Tor">
    <h1>Első admin létrehozása</h1>
    <p>Ez az oldal csak addig érhető el, amíg nincs egyetlen admin felhasználó sem.</p>
    <form method="post" action="{{ url('/admin/setup') }}">
        @csrf
        <label>Jelenlegi admin kód
            <input name="admin_key" type="password" required autocomplete="off">
            <small>Ugyanaz a kód, amivel eddig az admin.html-be lépett.</small>
        </label>
        @error('admin_key')<div class="error">{{ $message }}</div>@enderror
        <label>Név<input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
        @error('name')<div class="error">{{ $message }}</div>@enderror
        <label>E-mail<input name="email" type="email" value="{{ old('email') }}" required maxlength="254" autocomplete="username"></label>
        @error('email')<div class="error">{{ $message }}</div>@enderror
        <label>Jelszó<input name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"><small>Legalább 12 karakter.</small></label>
        @error('password')<div class="error">{{ $message }}</div>@enderror
        <label>Jelszó még egyszer<input name="password_confirmation" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></label>
        <button type="submit">Admin létrehozása és belépés</button>
    </form>
</main>
</body>
</html>
