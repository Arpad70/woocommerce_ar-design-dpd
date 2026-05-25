=== AR Design DPD for WooCommerce ===
Contributors: ardesign
Tags: shipping, woocommerce, dpd
Requires at least: 5.3
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 8.6.17
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Samostatný DPD modul pre WooCommerce spravovaný AR Design. Plugin vychádza z pôvodnej integrácie Webikon a ďalej ju rozvíja v samostatnom release cykle.

== Description ==
Plugin poskytuje jednoduché a rýchle riešenie na prenos údajov o objednaných prepravných službách s doručením na adresu príjemcu a do odberných miest.

Pomocou pluginu si môžete vo svojich **Woocommerce objednávkach** vyberať zo širokého portfólia produktov a služieb DPD, ako sú napríklad **DPD Home, DPD Classic, DPD 10:00, DPD 12:00, DPD 18:00 /DPD Guarantee** s doručením na adresu príjemcu a do odberných miest v rámci Slovenska a do 230 krajín sveta.

Plugin je prepojený cez API riešenie k [www.dpdshipper.sk](https://www.dpdshipper.sk). Všetky objednávky vytvorené týmto pluginom si budete môcť prezerať aj v prehľade objednávok v online aplikácii [DPD Shipper](https://www.dpdshipper.sk/login).

Aktuálny export zásielok používa **DPD SK shipper API**. Tracking synchronizácia používa **STATUSDATA** súbory z lokálneho adresára a voliteľne zo SFTP.

Fork spravuje **AR Design**. Pôvodná integračná báza od **Webikon** ostáva uvedená v projekte ako coworker foundation.

Pre ďalší vývoj v repozitári pozrite aj dokumenty **DEVELOPER_GUIDE.md** a **MAINTAINER_HANDOFF.md**.

= FUNKCIE PLUGINU =

* Jednoduché a rýchle vytváranie objednávok prepravy
* Nastavenie predvoleného produktu na každú objednávku, napr. DPD Home
* Export objednávok do DPD SK shipper API
* Úprava objednávky pred exportovaním do DPD
* Tlač prepravných štítkov vo formáte A4 alebo A6
* Vytváranie objednávok doručenia do odberných miest Pickup a Pickup Station (samoobslužného boxu)
* Možnosť pridania ďalších ID bankových účtov a zvozových adries
* Použitie dvoch referencii na úrovni objednávky
* Tracking synchronizácia cez STATUSDATA súbory a voliteľný SFTP download
* Možnosť nastavenia dopravy zdarma
* Možnosť nastavenia ceny dopravy podľa hmotností produktov v košíku.

== Installation ==
1. Stiahnite si plugin z WordPress.org
2. Nainštalujte a aktivujte si plugin

= Nastavenie pluginu =

1. Prejdite do nastavení **WooCommerce** -> **Doprava** -> **DPD nastavenia exportu**
2. Vyplňte **požadované údaje** pre pripojenie na API DPD Shipper
  * **ID delis**
  * **E-mail (Login) posielaný spolu s API kľúčom**
  * **API Kľúč**
3. Vyplňte **informácie pre doručenie** - štandardné nastavenia
  * **ID bankového účtu** - z [www.dpdshipper.sk](https://www.dpdshipper.sk) -> Nastavenia -> Bankové účty -> hodnota z **ID účtu**
  * **ID adresy pre zber** - z [www.dpdshipper.sk](https://www.dpdshipper.sk) ->  Nastavenia -> Zvozové adresy -> hodnota z **ID adresy**
  * **Doprava** - produkt, ktorý štandardne používate pre odosielanie balíkov
  * **Notifikácie** - Zapnutie/Vypnutie oznámení pri vybraných produktoch prepravy
4. Voliteľne nastavte tracking synchronizáciu cez **STATUSDATA directory** a prípadne SFTP prístup pre automatické sťahovanie STATUSDATA súborov
5. Spôsob odosielania objednávok do portálu DPD Shipper
  * **Z detailu objednávky:** V detaile objednávky v bočnom paneli máte možnosť odoslať objednávku do API. Máte tam tiež možnosť zmeniť štandardné nastavenia pre aktuálnu objednávku (napr. zmena produktu)
  * **Hromadné odoslanie objednávok do DPD Shipper portálu:** V zozname objednávok si označíte objednávky na odoslanie a v hromadných akciách si vyberiete možnosť **DPD Hromadný Export**.
6. Funkčnosť prepojenia si môžete **overiť vytvorením testovacej objednávky** a následným exportom

== Screenshots ==
1. Nastavenia pluginu
2. Zobrazenie stĺpca pre export do DPD v zozname WooCommerce objednávok
3. Zobrazenie možnosti hromadného exportu do DPD v zozname WooCommerce objednávok
4. Zobrazenie možnosti exportu do DPD v detaile WooCommerce produktu

== Changelog ==

= 8.6.14 =
* Fixed DPD STATUSDATA tracking so semantically weaker scans like `18 = Additional shipment information` no longer overwrite a more important current state such as `13 = Delivered` in the order tracking summary.

= 8.6.13 =
* Fixed legacy DPD Pickup / Pickup Station exports for orders missing both numeric `parcelshop_id` and `pus_id` by re-matching the pickup point from DPD API address metadata.
* Added a broader `Repair ParcelShop data` action in the DPD metabox for production support and follow-up exports.

= 8.6.10 =
* Added post-label workflow automation support including print confirmation handling and automatic order status transitions driven by carrier tracking updates.
* Added unified DPD tracking diagnostics to the order detail and order list, including mapped workflow status, shipment label, raw status code, service code, location, event time, and last sync.
* Persisted the latest DPD raw tracking status code and service code in the current tracking snapshot to improve support diagnostics and workflow troubleshooting.

= 8.6.9 =
* Fixed DPD Pickup / Pickup Station exports to resolve the correct numeric parcelshop ID before creating PSD labels.
* Prevented PSD exports from falling back to invalid public PUS IDs that could route labels to the wrong pickup point.
* Hardened the DPD map widget selection flow to forward dedicated parcelshop and PUS identifiers when available.

= 8.6.0 =
* Documentation update for the current AR Design architecture.
* Clarified that shipment export uses DPD SK shipper API.
* Clarified that tracking synchronization uses STATUSDATA files and optional SFTP download.
* Added repository documentation references for developers and maintainers.
* Removed non-DPD carrier automation. Those carrier integrations now live in separate fix modules.

= 8.5.0 (AR Design fork) =
* Initial AR Design standalone fork of the original DPD plugin.
* Added standalone repository and release/update channel standards.

= 8.5.0 =
* Added `wc_dpd_before_order_export` action hook fired before order export (passes $order and $data)
* Added `wc_dpd_after_order_export` action hook fired after successful order export (passes $order and $response)
* Added `wc_dpd_order_export_error` action hook fired on export error (passes $order and $exception)

= 8.4.0 =
* Added free shipping threshold option for weight-based shipping rates

= 8.3.0 =
* Extended locker filtering to support Z-Box
* Added admin validation to ensure at least one pickup point type (shops or lockers) remains enabled
* Improved shipping method configuration safety with client-side and server-side validation

= 8.2.1 =
* Fixed Czech Republic country code mapping (WooCommerce 'cs' to ISO3166 'CZ')

= 8.2.0 =
* Added language selection option for DPD map widget (Slovak, English, Hungarian, German, French)
* Slovak is set as the default language

= 8.1.1 =
* Fixed free shipping threshold logic - empty field now properly disables free shipping

= 8.1.0 =
* Fixed parcel shop validation for WooCommerce Blocks checkout
* Added filter 'wc_dpd_is_cart_or_checkout_page' for custom checkout page detection
* Improved WooCommerce Blocks compatibility for parcel shop selection validation
* Updated free shipping description hint

= 8.0.2 =
* Fixed export metabox method

= 8.0.1 =
* Fixed parcel weight calculation

= 8.0.0 =
* Added support for WooCommerce Blocks
* Added ability to set exact parcel weight before export

= 7.0.2 =
* Added validation for name and company name length

= 7.0.1 =
* Standardize weight unit to kg for parcelshop filtering

= 7.0.0 =
* Add ability to hide parcelshops from interactive map

= 6.0.1 =
* Handle dynamic map elements

= 6.0.0 =
* Added an option to enable a map widget that allows users to choose a parcelshop from an interactive map.
* Changed the `wc_dpd_cod_id` filter to accept an array of payment method IDs instead of a single method string.
* Added a new filter `wc_dpd_card_payment_ids` to specify an array of card payment method IDs.

= 5.0.3 =
* Fix pickup date that is not a Saturday, Sunday, or a holiday in Slovakia.
* Disable card payment for cash on delivery (COD) orders to countries other than Slovakia.
* Fixed deprecated warnings in the DpdExport class.

= 5.0.2 =
* Fixed tax for free shipping

= 5.0.1 =
* Added multisite support

= 5.0.0 =
* Added support for WooCommerce HPOS
* Added WooCommerce version check and updated the minimum required WC version
* Added filter for COD payment method ID wc_dpd_cod_id

= 4.0.4 =
* Replace package ID with international PUS ID

= 4.0.3 =
* Add reset button to order metabox

= 4.0.2 =
* Updated compatibility with PHP 7.4

= 4.0.1 =
* Add functionality to check for free shipping coupon

= 4.0.0 =
* Added parcelbox packages validation by weight or dimensions
* Added Alzabox packages validation by weight or dimensions
* Added Slovenska Posta packages validation by weight or dimensions
* Allow credit card payment for cod

= 3.0.2 =
* Added action wc_dpd_order_exported

= 3.0.1 =
* Added filter to modify export data
* Added filter to prevent export
* Fixed cod amount rounding that caused discrepancies in some transactions

= 3.0.0 =
* Added an option to set a value for free shipping
* Added an option to set shipping cost based on the weight of products in the cart
* Added an option to choose A4 or A6 labels format
* Fixed a decimal numbers error in shipping cost calculation
* Fixed a critical error in WordPress site health
* Pop-up can be now closed by pressing ESC or clicking outside
* Order number is included in variable symbol.
* Updated user manual

= 2.0.1 =
* Updated plugin description

= 2.0.0 =
* Added support for parcelshops, bulk download of labels, multiple addresses and bank accounts, adding reference texts to the order export and displaying the parcel number in the order list and order detail.

= 1.0.1 =
* Update readme.txt.

= 1.0.0 =
* First implementation.
