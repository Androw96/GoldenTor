# Golden Tor Consulting

Statikus weboldal és Python/SQLite backend kapcsolatfelvételhez, időpontfoglaláshoz, adminisztrációhoz és ügyfélportálhoz.

Részletes magyar használati útmutató: [HASZNALATI_UTMUTATO.md](HASZNALATI_UTMUTATO.md)

## Helyi indítás

```bash
cp .env.example .env
python3 server.py
```

Alapértelmezett cím: `http://127.0.0.1:4174/preindex.html`

Admin: `http://127.0.0.1:4174/admin.html`

Az admin alapértelmezett helyi belépése `admin` / `change-me-local`. Élesítés előtt kötelező erős `GOLDENTOR_ADMIN_PASSWORD` értéket megadni.

## E-mail

SMTP konfiguráció nélkül az elküldendő levelek a `data/outbox.log` fájlba kerülnek. SMTP használatához töltsd ki az `.env` megfelelő változóit.

## Adatbázis

Az SQLite adatbázis automatikusan létrejön: `data/goldentor.sqlite3`.
Induláskor, majd naponta automatikus mentés készül a `data/backups/` mappába; a rendszer a legutóbbi 14 mentést őrzi meg.

## Admin és automatikus értesítések

Az admin áttekintés foglalási, érdeklődői és konverziós mutatókat jelenít meg. A foglalás visszaigazolása, lemondása vagy lezárása e-mailt készít az ügyfélnek, a megerősített konzultációkhoz pedig 24 órás emlékeztető tartozik.

Valódi szakértői profil feltöltéséhez az admin Tartalom lapján használható a `Szakértői profilsablon`. A `name`, `role`, `bio`, `credentials` és `photo` mezőket kizárólag ellenőrzött, publikálható adatokkal kell kitölteni.

## Kalkulátorok

A `kalkulatorok.html` befektetési növekedés-, hiteltörlesztés- és védelmi tartalék-kalkulátort tartalmaz. Ezek tájékoztató modellek, nem minősülnek ajánlatnak vagy hozamígéretnek.

## Tesztek

```bash
python3 -m unittest discover -s tests
```

## Élesítés előtt

- hivatalos cégadatok és jogi szövegek véglegesítése;
- valódi szakértőnevek, portrék és képesítések feltöltése;
- SMTP és végleges publikus URL beállítása;
- Google/Microsoft OAuth kulcsok megadása, ha kétirányú naptárszinkron szükséges;
- HTTPS reverse proxy és rendszeres adatbázismentés beállítása.
