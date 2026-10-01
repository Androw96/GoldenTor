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

A kalkulátorok és a Valiora módszertan kizárólag bejelentkezés után érhetők el. A `kalkulatorok.html` befektetési növekedés-, hiteltörlesztés- és védelmi tartalék-kalkulátort tartalmaz. Ezek tájékoztató modellek, nem minősülnek ajánlatnak vagy hozamígéretnek.

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

## Ingyenes éles szerver

A teljes alkalmazás nem tölthető fel Google Sites-ba, mert a Google Sites nem futtat Python szervert vagy SQLite-adatbázist. A jelenlegi, belépéses ügyfélportálhoz az ingyenes Oracle Cloud Always Free Ubuntu VM-re való telepítés előkészítve megtalálható a [deploy/oracle-deployment.md](deploy/oracle-deployment.md) útmutatóban. A `deploy/oracle-bootstrap.sh` telepíti az alkalmazást, Nginxet és a HTTPS-t; közben a szolgáltatás csak belső hálózati porton marad elérhető.

Ha a meglévő UNAS webtárhelyet használjuk, az alkalmazás PHP/MySQL változata a `unas/` mappában található. Ez illeszkedik az UNAS PHP 8 és MySQL szolgáltatásához, és nem igényel külön Oracle-fiókot. Telepítési lépések: [unas/DEPLOYMENT.md](unas/DEPLOYMENT.md).

## Személyes ügyfélfiók

A `fiok.html` oldalon regisztráció, bejelentkezés, név/telefonszám szerkesztés és kijelentkezés érhető el. A fiók különálló a foglalási tokenes ügyfélportáltól; regisztrációval nem kapcsolunk össze korábbi foglalásokat ellenőrizetlen e-mail-cím alapján. A jelszó scrypt kivonatként, egyedi sóval tárolódik. A szerver által ellenőrzött munkamenet 8 órás, HttpOnly és SameSite=Lax sütivel; HTTPS publikus URL esetén Secure attribútummal.

A `GOLDENTOR_PUBLIC_URL` pontosan egyezzen a böngészőben használt origin értékével (protokoll, domain és port). Az ügyfélfiók API idegen eredetű POST kéréseket elutasít. Az éles oldalt HTTPS mögött kell futtatni. Az új oldalakat a `server.py` szolgálja ki: általános statikus tárhely vagy a védett fájlokat közvetlenül kiszolgáló reverse proxy nem biztosítja a hozzáférésvédelmet. A `private/valiora.html` csak hitelesített API-n át érhető el. A kalkulátor HTML és az eszközök JavaScriptje GET és HEAD esetén is védett.

A regisztráció jelenleg közvetlenül létrehozza a fiókot; e-mail-megerősítés és automatikus elfelejtettjelszó-folyamat nincs. A visszajelzéseket a szakértői oldal bejelentkezett űrlapja menti; az admin **Visszajelzések** lapján olvashatók, nyilvánosan nem jelennek meg.

## Megjelenés és fejlécvideó

A főoldal sorrendje: fejlécvideó → Rólunk (pénzügyi döntési rend és időtálló szemlélet) → Szolgáltatások → Kalkulátor → Blog → Kapcsolat. A színpaletta a Gutmann hivatalos weboldalának 2026. szeptember 18-án ellenőrzött színein alapul: `#1b251e`, `#668272`, `#f8f3ee`, `#ebf150`. Az adminban mentett egyedi színbeállítások továbbra is felülírhatják az alap tokeneket.

Az `assets/market-loop.mp4` saját, 10 másodperces, ismétlődő, illusztratív animáció; a számok nem élő árfolyamok. Szüneteltethető, csökkentett mozgás beállításnál állóképpel indul. Újragenerálás macOS-en: `swift scripts/generate_market_video.swift`. A videóhoz nem kell külső szolgáltatás.

## A megjelenés szerkesztése

A 2026. szeptember 20-i átdolgozás önálló `site.css` stíluslapot használ a főoldalon. Az aloldalakon ez a fájl biztosítja az egységes fejlécet, láblécet, tipográfiát és űrlapokat a régebbi `styles.css` elrendezései felett. A főoldal szolgáltatásonként egy kártyát tartalmaz, az ismétlődő hosszú blokkok a részletes aloldalakon olvashatók. A főoldali blogelőnézet a publikált adminisztrátori cikkeket is követi; tartalom nélkül a beépített cikkekre mutat.
