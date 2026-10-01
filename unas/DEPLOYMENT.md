# Golden Tor telepítése UNAS tárhelyre

Az UNAS webtárhely PHP 8, MySQL, FTP, cron és SSL szolgáltatását használja ez a változat. A `unas/` mappa a Python/SQLite háttér helyett fut: a nyilvános címek és a böngészős felület változatlan maradnak.

## Telepítés

1. Az UNAS tárhely adminfelületén hozd létre a `goldentor` MySQL adatbázist és egy csak ehhez tartozó MySQL felhasználót. Adj neki hosszú, egyedi jelszót.
2. Nyisd meg a PHPMyAdmin felületet, válaszd ki az új adatbázist, és importáld az `unas/schema.sql` fájlt.
3. A tárhely fájlkezelőjében vagy SFTP-n töltsd fel a projekt fájljait a `goldentor.hu` webgyökérkönyvtárába. A `.git`, `data`, `deploy`, `tests`, `scripts`, `server.py` és `accounts.py` mappák/fájlok nem szükségesek a feltöltéshez.
4. Másold az `unas/config.php.example` fájlt `unas/config.php` néven, majd töltsd ki a tényleges MySQL adatokkal és egy minimum 32 véletlen bájtból készült admin kulccsal. A fájlt a `.htaccess` tiltja a nyilvános eléréstől.
5. Állítsd a `goldentor.hu` és a `www.goldentor.hu` webhelyet erre a tárhelyre, majd kapcsold be az UNAS SSL tanúsítványát.
6. Nyisd meg a `https://goldentor.hu/api/health` címet. A válaszban `"ok":true` jelenik meg, ha a PHP és MySQL kapcsolat működik.

Az UNAS cron felületén hozz létre ötpercenként futó PHP feladatot az `unas/reminders.php` fájlhoz. Ez küldi ki a visszaigazolt konzultációk 24 órás emlékeztetőjét.

## Biztonság és adatok

- A felhasználói munkamenet HTTP-only, SameSite=Lax és HTTPS alatt Secure sütit használ.
- A kalkulátor, a kalkulációk mentése és a Valiora tartalom csak bejelentkezett fiókkal érhető el.
- Az admin API külön, hosszú admin kulccsal védett; ezt csak a Golden Tor belső adminfelülete használja.
- A kapcsolatfelvételi és fiók végpontok tízperces IP-alapú korlátozást kapnak.
- Az UNAS MySQL adatbázis csak a tárhelyről vagy PHPMyAdminból érhető el, ezért böngészőből nem publikus.

A PHP `mail()` függvényével küldhetők a foglalási és kapcsolatfelvételi értesítések a tárhelyen beállított `info@goldentor.hu` címről. A tényleges kézbesítést az UNAS tárhely mail beállításainak élesítése után kell ellenőrizni.
