# Golden Tor – Filament admin

Laravel 12 + Filament 5 adminfelület a meglévő UNAS MySQL adatbázisra.
Elérés élesítés után: **https://goldentor.hu/admin**

## Mit tud

| Menü | Tartalom |
|---|---|
| **Áttekintés** | közelgő konzultációk, visszaigazolásra váró kérések, új megkeresések, konverzió, ügyfélfiókok, 12 havi grafikon, témák szerinti megoszlás, közelgő időpontok egy kattintásos visszaigazolással |
| **Foglalások** | fülek (Közelgő / Visszaigazolásra vár / Visszaigazolt / Lezárt / Összes), visszaigazolás, lezárás, lemondás (az ügyfél ugyanazt az e-mailt kapja, mint eddig), dupla foglalás ellenőrzése, új foglalás felvétele, ügyfélportál link |
| **Megkeresések** | státusz közvetlenül a táblából, válasz e-mailben, tömeges lezárás/archiválás |
| **Ügyfélfiókok** | regisztrált ügyfelek, mentett kalkulációik és visszajelzéseik, adatmódosítás, új jelszó beállítása |
| **Mentett kalkulációk** | minden ügyfél-kalkuláció olvasható bontásban |
| **Visszajelzések** | csillagos értékelések szakértőnként |
| **Tartalom → Teljes szövegek** | a weboldal mind az 525 szövege oldalanként; ugyanez közvetlenül a weboldalon is szerkeszthető |
| **Tartalom → Blog** | cikkek létrehozása, sorrend, vázlat/publikált, képfeltöltés |
| **Tartalom → Oldalszövegek** | az oldalak nyitószövegei HU/EN/DE nyelven |
| **Tartalom → Szakértők** | szakértői profilok fotófeltöltéssel |
| **Tartalom → Design** | színek, fejléc hangulata |

Minden tartalom ugyanabba a `content` táblába kerül, amit a nyilvános oldal eddig is olvasott, így a weboldalon nem kell semmit átírni. A régi `admin.html` tartalékként megmarad.

## Felépítés

- Ez a mappa csak a saját kódot tartalmazza (*overlay*). A teljes Laravel alkalmazást a GitHub Actions rakja össze: `composer create-project laravel/laravel:^12.0`, + `filament/filament:^5.0`, + ez a mappa.
- Az UNAS-on PHP 8.1 fut, ezért Laravel 10. Ha a tárhelyen PHP 8.2+-ra váltasz, a workflow-ban a `php-version`, a `platform.php` és a `laravel/laravel:^12.0.0` átírható (Laravel 11-hez a skeleton eltér, ilyenkor a `build/patch-skeleton.php` is igazítandó).
- Szerveren: `/admin-panel/` (Laravel), ebből csak a `public/` érhető el kívülről. A gyökér `.htaccess` a `/admin` és `/livewire` címeket irányítja ide.
- Admin felhasználók külön táblában vannak (`admin_users`), a weboldal ügyfélfiókjaitól (`users`) függetlenül.

## Élesítés

1. Commitold és pushold a változásokat a `main` ágra. A push magától lefuttatja a buildet és a füsttesztet (telepítés nélkül).
2. GitHub → **Actions** → **Filament admin – build, teszt, UNAS telepítés** → **Run workflow** (a „Telepítés” pipa maradjon bekapcsolva).
   A workflow a már meglévő titkokat használja: `UNAS_FTP_HOST`, `UNAS_FTP_USERNAME`, `UNAS_FTP_PASSWORD`, `UNAS_DB_PASSWORD`, `UNAS_ADMIN_KEY`, `UNAS_SETUP_KEY`. Új titok nem kell.
3. Első alkalommal nyisd meg a **https://goldentor.hu/admin/setup** oldalt, add meg a mostani admin kódot (amivel az admin.html-be lépsz), és hozd létre a saját admin fiókodat. Ez az oldal ezután 404-et ad.
4. További adminokat a felületről nem lehet felvenni. Ha kell, szólj, és hozzáadok egy „Adminok” menüt.

Ha a szerveren hiányzik egy PHP-bővítmény (pl. `intl`), a telepítő a csere előtt megáll, és a workflow naplójában kiírja, mi hiányzik. Az élő oldal ilyenkor érintetlen marad. Az UNAS ügyfélszolgálatán vagy a tárhely PHP-beállításainál bekapcsolható.

## Helyi fejlesztés (opcionális)

```bash
composer create-project laravel/laravel:^12.0.0 admin-local && cd admin-local
composer remove laravel/sanctum && composer require filament/filament:^5.0
php ../filament-admin/build/patch-skeleton.php && rsync -a --exclude build/ --exclude deploy/ ../filament-admin/ ./
php artisan filament:assets && php artisan test
```

## Szövegek szerkesztése a weboldalon

Belépett adminként a nyilvános oldalakon jobb alul megjelenik a **„✎ Szövegek szerkesztése”** gomb.
Bekapcsolva minden szerkeszthető szöveg szaggatott keretet kap: rákattintasz, átírod, majd **Mentés**.
A kijelölt szöveg az **„Eredeti visszaállítása”** gombbal visszaállítható. A fejléc és a lábléc szövegei minden oldalon közösek.

- A szövegek azonosítóit a `scripts/tag-editable-text.py` teszi a HTML-be (`data-gt-text`). Ha új szöveget írsz a HTML-be,
  futtasd le újra: `python3 scripts/tag-editable-text.py`. A meglévő azonosítók nem változnak. A CI `--check` móddal ellenőrzi.
- A mentett szövegek a `content` tábla `page_text` / `hu` rekordjában vannak; a weboldal a `site-text.js`-sel tölti be.
- Kimaradnak a menüpontok, a nyelvválasztó, az űrlapmezők címkéi és a JavaScript által frissített értékek (ezeket a kód írná felül).
- A szerkesztés csak magyar nyelven érvényes; angol/német nézetben az eredeti fordítások látszanak.
