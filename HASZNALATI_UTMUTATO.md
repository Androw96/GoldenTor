# Golden Tor Consulting - Használati útmutató

## 1. A weboldal elindítása

Nyisson terminált a projekt mappájában:

```bash
cd /Users/ozymandias/Documents/GoldenTor
python3 server.py
```

A weboldal ezután az alábbi címen érhető el:

- Főoldal: `http://127.0.0.1:4174/index.html`
- Blog: `http://127.0.0.1:4174/blog.html`
- Adminfelület: `http://127.0.0.1:4174/admin.html`

A szerver leállításához a terminálban nyomja meg a `Ctrl+C` billentyűkombinációt.

## 2. Látogatói útvonal

### Letisztult főoldali belépés

1. A látogató közvetlenül az `index.html` oldalt nyitja meg.
2. A főoldal egy sötétkék-arany, bizalmi hangulatú vizuális nyitóval indul.
3. Az első szakasz rögtön személyes konzultációra vagy élethelyzet szerinti tájékozódásra vezet.
4. A korábbi `preindex.html` és `welcome.html` útvonalak automatikusan az új főoldalra irányítanak.

### Élethelyzet alapú tájékozódás

A főoldalon külön kártyák segítenek felismerni, melyik ügyfélhelyzethez kapcsolódhat a látogató:

- felszabadult tőke vállalkozás után;
- meglévő, de átvilágítást igénylő portfólió;
- öröklés, életforduló vagy nagyobb döntés előtti helyzet.

Ezek után a látogató a szolgáltatási oldalak, a kalkulátorok, a blog vagy az időpontfoglalás felé tud továbblépni.

## 3. Navigáció

Asztali nézetben a fő navigáció a fejlécben látható. Mobilon a háromvonalas menügombbal nyitható meg.

A jobb felső nyelvválasztó lehetőségei:

- `HU` - magyar;
- `EN` - angol;
- `DE` - német.

A kiválasztott nyelvet a böngésző megjegyzi. A hosszú szakmai szövegek végleges angol és német változatát publikálás előtt szakmailag ellenőrizni kell.

## 4. Szolgáltatási oldalak

Minden terület külön oldalon található:

- `befektetes.html` - befektetési stratégia és vagyonépítés;
- `ingatlan.html` - ingatlanstratégia és megtérülési szempontok;
- `finanszirozas.html` - finanszírozási konstrukciók és stresszteszt;
- `biztositas.html` - kockázati és vagyonvédelmi megoldások;
- `szakertoink.html` - szakértői átvilágítás, folyamatok és esettípusok.
- `blog.html` - közérthető pénzügyi cikkek és döntési segédletek.

Az oldalak tartalomjegyzéke az adott részhez görget. A cselekvésre ösztönző gombok az időpontfoglaláshoz vagy a kapcsolatfelvételhez vezetnek.

## 5. Pénzügyi kalkulátorok

A kalkulátorok a `kalkulatorok.html` oldalon érhetők el.

### Vagyonépítési kalkulátor

Megadható:

- kezdőtőke;
- havi megtakarítás;
- feltételezett éves hozam;
- időtáv.

Az eredmény megmutatja a befizetett összeget, a becsült növekményt és a becsült jövőértéket.

### Hitelkalkulátor

Megadható:

- hitelösszeg;
- éves kamat;
- futamidő.

Az eredmény tartalmazza a becsült havi törlesztőt, a teljes visszafizetést és a kamatterhet.

### Védelmi tartalék kalkulátor

Megadható:

- havi alapkiadás;
- eltartottak száma;
- biztonsági időszak;
- jelenlegi likvid tartalék.

Az eredmény megmutatja a javasolt tartalékszintet, a jelenlegi fedezetet és a még felépítendő összeget.

> A kalkulátorok tájékoztató modellek. Nem minősülnek ajánlatnak, személyre szabott pénzügyi tanácsnak vagy hozamígéretnek.

## 6. Kapcsolatfelvétel

A főoldal alján található kapcsolatfelvételi űrlapon kötelező megadni:

- nevet;
- e-mail-címet;
- érdeklődési területet;
- üzenetet;
- adatkezelési hozzájárulást.

A rendszer sikeres küldéskor:

1. ellenőrzi a megadott adatokat;
2. elmenti a megkeresést az adatbázisba;
3. értesítést készít a tanácsadó számára;
4. megjeleníti az érdeklődőt az adminfelületen.

## 7. Időpontfoglalás

Az időpontfoglalás az `idopont.html` oldalon érhető el.

A látogatónak ki kell választania:

- a konzultáció témáját;
- a találkozás formáját;
- egy jövőbeli munkanapot;
- egy elérhető időpontot.

A rendszer nem engedi ugyanazt az aktív időpontot kétszer lefoglalni. Sikeres rögzítés után egyedi ügyfélportál-link készül.

## 8. Ügyfélportál

Az ügyfélportál egy egyedi tokennel védett URL-en érhető el. A linket a foglalás rögzítése után kapja meg az ügyfél.

Az ügyfél itt láthatja:

- a foglalás állapotát;
- a konzultáció témáját;
- a találkozás módját;
- a dátumot és időpontot.

További lehetőségek:

- `.ics` naptárfájl letöltése;
- hozzáadás Google Naptárhoz;
- aktív foglalás lemondása.

Az egyedi ügyfélportál-linket bizalmas adatként kell kezelni.

## 9. Adminfelület

Admin URL: `http://127.0.0.1:4174/admin.html`

Helyi fejlesztői belépés:

- Felhasználónév: `admin`
- Jelszó: `change-me-local`

> Élesítés előtt kötelező erős, egyedi adminjelszót beállítani.

### Áttekintés

Megjeleníti:

- az összes foglalás számát;
- az érdeklődők számát;
- a közelgő időpontokat;
- az adott havi foglalásokat;
- a visszaigazolási arányt;
- a leggyakoribb foglalási témákat.

### Foglalások

A foglalások állapota módosítható:

- `requested` - beérkezett időpontkérés;
- `confirmed` - visszaigazolt időpont;
- `completed` - lezárt konzultáció;
- `cancelled` - lemondott időpont.

Státuszváltozáskor a rendszer automatikus ügyfélértesítést készít. A megerősített foglalásokhoz 24 órás emlékeztető tartozik.

### Kapcsolatok

Itt jelennek meg a főoldali kapcsolatfelvételi űrlapon érkezett megkeresések és az ügyfél által megadott adatok.

### Tartalomkezelés

A tartalmi rekordok nyelvenként menthetők. A mezők:

- kulcs;
- nyelv;
- cím;
- JSON-formátumú tartalom.

## 10. Szakértői profil feltöltése

1. Nyissa meg az adminfelület `Tartalom` lapját.
2. Kattintson a `Szakértői profilsablon` gombra.
3. Töltse ki a következő mezőket:
   - `name` - szakértő neve;
   - `role` - szakterület vagy pozíció;
   - `bio` - rövid szakmai bemutatkozás;
   - `credentials` - képesítések listája;
   - `photo` - a portré elérési útvonala.
4. Válassza ki a tartalom nyelvét.
5. Mentse a tartalmat.

Példa:

```json
{
  "profiles": [
    {
      "name": "Minta Név",
      "role": "Befektetési szakértő",
      "bio": "Ellenőrzött és jóváhagyott szakmai bemutatkozás.",
      "credentials": ["Képesítés", "Szakmai tagság"],
      "photo": "assets/minta-nev.webp"
    }
  ]
}
```

Csak ellenőrzött, publikálható neveket, portrékat és képesítéseket szabad feltölteni.

## 11. E-mail-beállítás

SMTP-konfiguráció nélkül a rendszer az elküldendő leveleket a következő fájlba írja:

`data/outbox.log`

Valódi e-mail-küldéshez az `.env` fájlban szükséges megadni:

```text
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=info@goldentor.hu
CONTACT_RECIPIENT=info@goldentor.hu
```

A szervert a beállítások módosítása után újra kell indítani.

## 12. Adatbázis és mentések

Az adatbázis helye:

`data/goldentor.sqlite3`

Automatikus mentések:

`data/backups/`

A rendszer induláskor, majd naponta mentést készít, és a legutóbbi 14 példányt őrzi meg. Éles környezetben emellett külső, tárhelyszintű mentést is ajánlott használni.

## 13. Jogi és adatvédelmi oldalak

A láblécből elérhető:

- impresszum;
- adatkezelési tájékoztató;
- jogi nyilatkozat;
- cookie-beállítások.

Élesítés előtt a cégadatokat és a jogi szövegeket jogi szakértővel ellenőriztetni kell.

## 14. Gyakori problémák

### Az oldal nem érhető el

Ellenőrizze, hogy fut-e a `python3 server.py` parancs, és a terminálban megjelent-e a következő cím:

`http://127.0.0.1:4174`

### A módosítás nem látható

Frissítse az oldalt. Ha szükséges, használjon gyorsítótár nélküli újratöltést:

- macOS: `Command+Shift+R`;
- Windows/Linux: `Ctrl+Shift+R`.

### Nem érkezik valódi e-mail

Ellenőrizze az SMTP-beállításokat. SMTP nélkül a levelek a `data/outbox.log` fájlban találhatók.

### Nem jelenik meg a szakértői profil

Ellenőrizze, hogy:

- a tartalmi kulcs `experts`;
- a megfelelő nyelv lett kiválasztva;
- a JSON érvényes;
- a `name`, `role` és `bio` mezők ki vannak töltve;
- a portréfájl valóban létezik.

## 15. Rövid bemutatási útvonal

1. Nyissa meg az `index.html` oldalt.
2. Mutassa be a letisztult, sötétkék-arany főoldali nyitót.
3. Válasszon egy élethelyzet-kártyát vagy szolgáltatási irányt.
4. Mutasson meg egy részletes szolgáltatási oldalt.
5. Próbálja ki a vagyonépítési kalkulátort.
6. Mutassa be az időpontfoglalást.
7. Nyissa meg az admin KPI-panelt.
8. Mutassa meg a foglalási státuszok és a CMS kezelését.

## 16. Élesítés előtti ellenőrzőlista

- erős adminjelszó beállítása;
- végleges domain és HTTPS;
- SMTP-adatok beállítása;
- hivatalos cégadatok ellenőrzése;
- jogi dokumentumok jóváhagyása;
- valódi szakértői adatok feltöltése;
- angol és német szakmai fordítások ellenőrzése;
- külső adatbázismentés beállítása;
- minden űrlap és e-mail tesztelése;
- mobil- és asztali böngészőteszt.
