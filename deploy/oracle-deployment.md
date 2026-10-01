# Golden Tor ingyenes élesítése Oracle Cloudon

Ez a telepítés a jelenlegi Python/SQLite alkalmazást futtatja, ezért a belépés, a kalkulációk mentése és az ügyféladatok is működnek. Google Sites-ba ezt a rendszert nem lehet feltölteni: az csak beágyazni tud külső weboldalt, a Python alkalmazást és az adatbázist nem futtatja.

## 1. Oracle-fiók és szerver

Az Oracle Cloud [Always Free](https://www.oracle.com/cloud/free/) fiók létrehozásához az Oracle saját azonosítási folyamatában telefonszámot és bankkártyát kérhet. Ezt a fióktulajdonosnak kell jóváhagynia; a kártya- vagy belépési adatokat ne add át senkinek.

A fiókban hozz létre egy **Compute instance**-ot ezekkel az értékekkel:

- név: `goldentor-web`;
- rendszer: **Ubuntu 24.04**;
- alakzat: **VM.Standard.E2.1.Micro (Always Free eligible)**;
- nyilvános IPv4 cím: bekapcsolva;
- SSH-kulcs: töltsd le és őrizd meg; ne küldd el e-mailben vagy chatben.

A hálózati biztonsági listában engedélyezd a bejövő TCP `80` és `443` portot minden címről. Az SSH (`22`) maradjon csak a saját IP-címedről elérhető, ha az Oracle felülete ezt támogatja.

## 2. DNS átállítása az UNAS-ban

Amint megvan a VM nyilvános IPv4 címe, az UNAS DNS-zónában cseréld le a jelenlegi Google Sites rekordokat:

| Név | Típus | Érték |
| --- | --- | --- |
| `goldentor.hu` (gyökér) | `A` | a VM nyilvános IPv4 címe |
| `www.goldentor.hu` | `A` | ugyanaz a VM nyilvános IPv4 címe |

Távolítsd el a Google Sites-hoz tartozó gyökér `A` rekordokat (`216.239.*`) és a `www` `CNAME` rekordot (`ghs.googlehosted.com`). Az MX és SPF/DKIM/DMARC levelezési rekordokhoz ne nyúlj. A DNS-változás után csak akkor indítsd a következő lépést, ha mindkét név az új IP-címre mutat.

## 3. Telepítés az Oracle gépen

Az Oracle Cloud Shellben vagy saját terminálból jelentkezz be az SSH-kulccsal, majd futtasd:

```bash
git clone --branch reorg https://github.com/Androw96/GoldenTor.git
cd GoldenTor
sudo bash deploy/oracle-bootstrap.sh --domain goldentor.hu --email SAJAT-EMAIL-CIMED
```

A parancs telepíti az Nginxet, a Python futtatókörnyezetet, a systemd szolgáltatást és a Let's Encrypt HTTPS tanúsítványt. A szerver csak a belső `127.0.0.1:4174` címen fut; kívülről csak a HTTPS-es Nginx érhető el. Az adatbázis `/opt/goldentor/data/goldentor.sqlite3`, a napi 14 helyi mentés pedig `/opt/goldentor/data/backups/` alatt található.

Az admin kódot a telepítés véletlenszerűen hozza létre. A szerveren, saját SSH-kapcsolaton keresztül érhető el:

```bash
sudo grep '^GOLDENTOR_ADMIN_KEY=' /opt/goldentor/.env
```

## 4. Ellenőrzés és üzemeltetés

Telepítés után ellenőrizd:

```bash
curl -fsS https://goldentor.hu/api/health
sudo systemctl status goldentor
sudo nginx -t
```

SMTP nélkül az értesítések a szerver `data/outbox.log` fájljába kerülnek. Valódi automatikus e-mailekhez később a `/opt/goldentor/.env` `SMTP_*` változóit kell kitölteni, majd `sudo systemctl restart goldentor` parancsot futtatni.

Új kiadás telepítéséhez:

```bash
cd /opt/goldentor
sudo git pull --ff-only origin reorg
sudo systemctl restart goldentor
```

Az Oracle Always Free erőforrás használati és rendelkezésreállási feltételei változhatnak; a fiók admin felületén ellenőrizd, hogy az instance "Always Free eligible" jelölést kapott.
