<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Lejárt munkamenet – Golden Tor Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #141c17; color: #f8f3ee; font: 16px/1.6 system-ui, sans-serif; }
        main { width: 100%; max-width: 460px; }
        h1 { font-size: 26px; line-height: 1.3; }
        p { color: #b9c4bc; }
        a { display: inline-block; margin-top: 12px; padding: 10px 18px; background: #b8962e; border-radius: 6px; color: #141c17; text-decoration: none; font-weight: 600; }
        a:focus-visible { outline: 3px solid white; outline-offset: 4px; }
    </style>
</head>
<body>
<main>
    <h1>A munkamenet lejárt</h1>
    <p>Az űrlap biztonsági azonosítója már nem érvényes. Nyissa meg újra az oldalt, majd töltse ki az űrlapot. Ez a beküldés nem mentett adatokat.</p>
    <a href="{{ url(request()->is('admin/setup') ? '/admin/setup' : '/admin/login') }}">Az oldal újranyitása</a>
</main>
</body>
</html>
