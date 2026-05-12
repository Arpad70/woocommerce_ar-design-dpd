# Developer Guide

Technický přehled pluginu `AR Design DPD for WooCommerce` pro další vývojáře a code review.

## Aktuální architektura

Plugin je dnes postavený na těchto aktivních tocích:

- **export zásilek:** DPD SK shipper JSON-RPC API přes `https://capi.dpd.sk/shipment/json`
- **tracking zásilek:** import `STATUSDATA` souborů z lokálního adresáře, volitelně stažených ze SFTP
- **parcelshop lookup:** nadále používá starší endpoint `https://api.dpd.sk/parcelshop/json`

## Co už plugin záměrně nepoužívá

Tyto větve byly odstraněny z aktivního kódu a neměly by se znovu zavádět bez explicitního rozhodnutí:

- legacy **NST export fallback**
- legacy **tracking API polling** přes konfigurovatelné tracking URL + token
- admin settings pole pro NST a tracking polling

## Hlavní toky

### 1. Export objednávky

Vstupní tok:

1. `Order::export()`
2. `DpdExport::doExport()`
3. `DpdExport::getShipperRequestData()`
4. `Client::export()`
5. `Client::exportViaShipper()`

Důležité vlastnosti exportu:

- autentizace používá `DELIS ID`, `login email`, `API key`
- výchozí endpoint je `shipment/json`
- výchozí create metoda je `createV3`, ale je filtrovatelná přes `ard_dpd_shipper_create_method`
- ParcelShop zásilky se přepínají na produkt `17`
- země se převádí přes `league/iso3166`

### 2. Tracking zásilky

Vstupní tok:

1. `Tracking::syncOpenShipments()`
2. `Tracking::importStatusDataDirectory()`
3. volitelně `Tracking::downloadStatusDataFromSftp()`
4. `Tracking::importStatusDataFile()`
5. `Tracking::storeTrackingSnapshot()`
6. `Shipment::storeShipmentData()` / `Shipment::markDelivered()`

Důležité vlastnosti trackingu:

- tracking funguje jen při zapnutém `Enable tracking sync`
- synchronizace dnes očekává **STATUSDATA directory**
- pokud je nakonfigurováno SFTP, soubory se nejdřív stáhnou do lokálního adresáře
- doručení může automaticky dokončit WooCommerce objednávku

### 3. ParcelShop lookup

Tok zůstává aktivní mimo shipper export:

- `Client::searchParcelShop()` používá starší `parcelshop/json` endpoint
- `Client::LEGACY_URL` a interní `call()` jsou v kódu ponechány právě kvůli ParcelShop lookupu

To je úmyslné. Nejde o mrtvý legacy export kód.

## Důležité soubory

### Export a nastavení

- `includes/DpdExportSettings.php`
  - centrální admin settings
  - aktivní option keys pro shipper export, STATUSDATA tracking a map widget
- `includes/DpdExport.php`
  - sestavení shipper payloadu
- `includes/Client.php`
  - transportní vrstva pro shipper export, tisk štítků a parcelshop lookup
- `includes/Order.php`
  - orchestrace exportu a bulk label download
- `includes/OrderMetabox.php`
  - export / reset / manuální STATUSDATA import z detailu objednávky

### Tracking a shipment normalizace

- `includes/Tracking.php`
  - STATUSDATA parsing, SFTP download, mapování stavů, sync do order meta
- `includes/Shipment.php`
  - normalizovaný shipment model
- `includes/Automation.php`
  - návazné workflow po doručení

### Bootstrap a kompatibilita

- `ar-design-dpd.php`
  - plugin bootstrap
  - compatibility konstanty a legacy class aliasy pro starší integrace
- `includes/helpers.php`
  - helpery pro dual-hook kompatibilitu (`legacy_hook` + `new_hook`)

## Aktivní admin nastavení

V adminu mají zůstat jen aktuálně používané volby:

### Export
- DELIS ID
- DPD login email
- DPD API key
- Default DPD product
- Label print format
- Bank account ID
- ID of the collection address
- Notifications
- Labels format

### Map widget
- Enable Map Widget
- Map API Key
- Language

### Tracking / STATUSDATA
- Enable tracking sync
- STATUSDATA directory
- STATUSDATA SFTP host
- STATUSDATA SFTP port
- STATUSDATA SFTP username
- STATUSDATA SFTP password
- STATUSDATA SFTP remote directory
- STATUSDATA SFTP archive directory
- Autocomplete delivered orders

## Konvence pro další vývoj

### Co držet

- zachovat shipper export jako jedinou aktivní exportní větev
- zachovat STATUSDATA jako jediný aktivní tracking mechanismus
- zachovat backward compatibility bootstrap, pokud není jasný důvod ji odstranit
- preferovat malé inkrementální změny a ověření přes `php -l`

### Čemu se vyhnout

- nevracet do UI NST nebo tracking polling settings
- nepřidávat nový tracking API fallback „jen pro jistotu"
- nemačkat ParcelShop endpoint dohromady s exportní architekturou; je to samostatný aktivní tok

## Ověření po změnách

Minimální sanity check po PHP úpravách:

- `php -l` nad upravenými soubory
- editor diagnostics bez chyb
- při změně exportu ověřit:
  - export objednávky
  - návrat `mpsid` / `parcelno`
  - tisk štítku
- při změně trackingu ověřit:
  - import lokálního STATUSDATA souboru
  - případně SFTP download a archivaci
  - propsání stavu do objednávky a shipment meta

## Poznámka k buildu

Adresář `build/` obsahuje distribuční artefakty. Zdroj pravdy pro vývoj jsou kořenové soubory pluginu (`includes/`, `public/`, `templates/`, root markdown dokumentace). Pokud měníte zdrojové soubory, zvažte následné přegenerování release buildu podle release workflow projektu.
