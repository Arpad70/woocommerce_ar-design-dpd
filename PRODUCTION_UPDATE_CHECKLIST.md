# Production Update Checklist

## Pred release

- [ ] verzia v `VERSION` je správna
- [ ] `Version:` v `ar-design-dpd.php` zodpovedá `VERSION`
- [ ] `AR_DESIGN_DPD_VERSION` zodpovedá `VERSION`
- [ ] `Update URI` smeruje na `Arpad70/woocommerce_ar-design-dpd`
- [ ] `php scripts/verify-version-consistency.php` prejde bez chyby
- [ ] `bash scripts/build-plugin.sh` vytvorí ZIP bez chýb
- [ ] GitHub repo existuje a je dostupné
- [ ] tag `v<version>` bol pushnutý
- [ ] GitHub Release obsahuje asset `ar-design-dpd.zip`

## Pred produkčným update

- [ ] vytvorená záloha databázy
- [ ] vytvorená záloha adresára `wp-content/plugins/ar-design-dpd`
- [ ] WooCommerce je aktívny
- [ ] administrátor vie, kedy bude update vykonaný
- [ ] existuje prístup k serveru / SSH pre prípad rollbacku

## Po update vo WordPresse

- [ ] plugin `ar-design-dpd` zostal aktívny
- [ ] nastavenia DPD sa načítajú bez chyby
- [ ] export objednávky funguje
- [ ] štítok sa vygeneruje alebo stiahne bez chyby
- [ ] parcelshop výber funguje v checkoute
- [ ] nie sú nové fatálne chyby v PHP logu

## Rollback plán

1. deaktivovať plugin,
2. obnoviť zálohovaný adresár pluginu,
3. ak treba, obnoviť databázu,
4. znovu aktivovať plugin,
5. overiť export a nastavenia,
6. skontrolovať PHP log.
