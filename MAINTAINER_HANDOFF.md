# Maintainer Handoff

Praktický handoff dokument pro maintainera pluginu `AR Design DPD for WooCommerce`.

Tento soubor doplňuje `DEVELOPER_GUIDE.md`:

- `DEVELOPER_GUIDE.md` vysvětluje architekturu a důležité třídy
- `MAINTAINER_HANDOFF.md` popisuje, co zkontrolovat před nasazením, po nasazení a při incidentu

## Co je dnes aktivní

Držte se těchto aktivních toků:

- **export zásilek:** DPD SK shipper JSON-RPC API
- **tracking:** `STATUSDATA` import z lokálního adresáře, volitelně se stažením přes SFTP
- **parcelshop lookup:** starší `parcelshop/json` endpoint pro výběr ParcelShopu

## Co se nemá vracet

Bez explicitního rozhodnutí nevracet do pluginu:

- NST export fallback
- tracking API polling přes token + URL
- admin nastavení pro NST nebo tracking polling

## Před nasazením změn

Před každým release nebo ručním nasazením zkontrolujte:

- sedí verze v `VERSION`, plugin headeru a `AR_DESIGN_DPD_VERSION`
- je aktualizovaný `CHANGELOG.md`
- změny ve zdrojích dávají smysl i vůči `build/` artefaktům
- jsou nastavené aktivní DPD údaje v adminu:
  - `DELIS ID`
  - `DPD login email`
  - `DPD API key`
  - `ID of the collection address`
- pokud je zapnutý tracking, existuje a je zapisovatelný `STATUSDATA directory`
- pokud se používá SFTP, jsou vyplněné a ověřené přístupové údaje

## Rychlý smoke test po nasazení

### Export

Ověřte aspoň jednu testovací objednávku:

- export objednávky proběhne bez chyby
- do objednávky se uloží `mpsid` a/nebo `parcelno`
- jde stáhnout nebo vygenerovat štítek

### Tracking

Ověřte aspoň jeden tracking scénář:

- plugin najde lokální `STATUSDATA` soubor nebo stáhne data přes SFTP
- import proběhne bez fatální chyby
- stav se propsal do order/shipment meta
- pokud je zásilka doručená, funguje očekávané autocomplete chování

### ParcelShop

Pokud checkout používá ParcelShop:

- mapa nebo lookup vrací pobočky
- uložený výběr pobočky projde do objednávky
- ParcelShop export nepoškodí shipper export flow

## Když je rozbitý export

Typické symptomy:

- objednávka nejde exportovat
- chybí `mpsid` nebo `parcelno`
- negeneruje se štítek
- API vrací validační chyby nebo autentizační chyby

Co zkontrolovat:

1. jsou správně vyplněné `DELIS ID`, email a API key
2. je dostupný endpoint `https://api.dpd.sk/shipment/json`
3. odpovídá zvolený DPD produkt očekávanému typu zásilky
4. nejsou rozbité adresní nebo parcel data v payloadu
5. nebyla omylem zavedena změna, která obchází `Client::exportViaShipper()`

Podezřelá místa v kódu:

- `includes/DpdExport.php`
- `includes/Client.php`
- `includes/Order.php`
- `includes/OrderMetabox.php`

## Když je rozbitý tracking

Typické symptomy:

- objednávky se po doručení neaktualizují
- ruční import `STATUSDATA` nic nenajde
- SFTP import nestahuje soubory
- stav zásilky zůstává starý i po importu

Co zkontrolovat:

1. je zapnuté `Enable tracking sync`
2. existuje `STATUSDATA directory` a plugin do něj vidí
3. mají STATUSDATA soubory očekávaný formát
4. při SFTP jsou správně host, port, uživatel, heslo i remote directory
5. archivace po importu nepadá na oprávněních
6. nebyla omylem odstraněna vazba mezi tracking číslem a objednávkou

Podezřelá místa v kódu:

- `includes/Tracking.php`
- `includes/Shipment.php`
- `includes/Automation.php`
- order meta související s tracking číslem a shipment snapshotem

## Když je rozbitý ParcelShop

Typické symptomy:

- mapa nevrací odběrná místa
- zákazník neuloží ParcelShop do checkoutu
- export ParcelShop zásilky používá špatný produkt nebo chybí data pobočky

Co zkontrolovat:

1. není rozbitá konfigurace map widgetu
2. funguje lookup na `parcelshop/json`
3. změna v exportu nepoškodila speciální ParcelShop větev
4. frontend i backend pracují se stejnou sadou ParcelShop dat

Podezřelá místa v kódu:

- `includes/Client.php`
- checkout / map widget integrace
- části exportu, které přepínají produkt na ParcelShop variantu

## Kde hledat jako první

Pro rychlou orientaci:

- `README.md` — repo kontext a release základ
- `DEVELOPER_GUIDE.md` — architektura a aktivní toky
- `CHANGELOG.md` — co se měnilo
- `includes/DpdExportSettings.php` — aktivní admin settings
- `includes/DpdExport.php` — tvorba export payloadu
- `includes/Client.php` — API volání
- `includes/Tracking.php` — STATUSDATA a SFTP

## Minimální ověření po PHP změně

Doporučené minimum:

- syntax check přes `php -l` nad upravenými soubory
- editor diagnostics bez nových chyb
- alespoň jeden realistický exportní nebo tracking test podle typu změny

## Release poznámka

Zdroj pravdy jsou kořenové soubory pluginu. Pokud upravujete zdroj, zvažte i následné přegenerování distribučních artefaktů v `build/` podle release workflow projektu.

## Praktické pravidlo

Když si maintainer není jistý, jestli má přidat fallback, platí jednoduché pravidlo:

> Nejdřív ověřte aktuální aktivní architekturu. U tohoto pluginu je správný směr jednodušší tok, ne další legacy odbočka.
