# Golden Tor Consulting

Statikus weboldal és Python/SQLite backend kapcsolatfelvételhez, időpontfoglaláshoz, adminisztrációhoz és ügyfélportálhoz.

Részletes magyar használati útmutató: [HASZNALATI_UTMUTATO.md](HASZNALATI_UTMUTATO.md)

## Helyi indítás

```bash
cp .env.example .env
python3 server.py
```

Alapértelmezett cím: `http://127.0.0.1:4174/index.html`

Admin: `http://127.0.0.1:4174/admin.html`

Az adminfelület admin kóddal lép be. Élesítés előtt kötelező erős, hosszú `GOLDENTOR_ADMIN_KEY` értéket megadni az `.env` fájlban. Ha nincs megadva, a szerver indításkor ideiglenes helyi admin kódot generál és kiír a terminálra. A régi Basic Auth változók (`GOLDENTOR_ADMIN_USER`, `GOLDENTOR_ADMIN_PASSWORD`) kompatibilitási okból megmaradtak, de az alapértelmezett jelszóval nem használhatók.

## E-mail

SMTP konfiguráció nélkül az elküldendő levelek a `data/outbox.log` fájlba kerülnek. SMTP használatához töltsd ki az `.env` megfelelő változóit.

## Adatbázis

Az SQLite adatbázis automatikusan létrejön: `data/goldentor.sqlite3`.
Induláskor, majd naponta automatikus mentés készül a `data/backups/` mappába; a rendszer a legutóbbi 14 mentést őrzi meg.

## Admin és automatikus értesítések

Az admin áttekintés foglalási, érdeklődői és konverziós mutatókat jelenít meg. A megkeresések státusza jelölhető, a foglalás visszaigazolása, lemondása vagy lezárása e-mailt készít az ügyfélnek, a megerősített konzultációkhoz pedig 24 órás emlékeztető tartozik.

A `Szövegek` admin lapon a főoldal és a fő aloldalak kiemelt nyitó szövegei szerkeszthetők. A `Blog` lapon a publikus blogbejegyzések bővíthetők: új cikk, vázlat/publikált státusz, kép, URL azonosító, rövid bevezető, kiemelt pontok és teljes cikk szöveg kezelhető. A `Design` lapon a fő arany/sötétkék színek és a hero hangulata állítható. Mentés után a nyilvános oldal a szerveroldali tartalmi rekordból tölti be az új tartalmat vagy design beállítást.

Valódi szakértői profil feltöltéséhez az admin `JSON tartalom` lapján használható a `Szakértői profilsablon`. A `name`, `role`, `bio`, `credentials` és `photo` mezőket kizárólag ellenőrzött, publikálható adatokkal kell kitölteni.

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
