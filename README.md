# AR Design DPD for WooCommerce

Samostatný DPD modul pre WooCommerce spravovaný AR Design.

## Základné princípy

- plugin je samostatný modul vo vlastnej zložke a vo vlastnom repozitári,
- WordPress je host prostredie, nie hlavný zdrojový domov pluginu,
- release a aktualizácie sa riadia GitHub Releases,
- licenčný model zostáva `GPLv2`, rovnako ako v pôvodnom plugine,
- pôvodná integračná báza od Webikon zostáva uvedená ako coworker foundation projektu.

## Repo a update kanál

- kanonický GitHub repozitár: `Arpad70/woocommerce_ar-design-dpd`
- release asset pre updater: `ar-design-dpd.zip`
- verzia pluginu sa riadi súborom `VERSION`

## Release štandard

- verzia musí byť konzistentná v `VERSION`, plugin headeri a konštante `AR_DESIGN_DPD_VERSION`
- zmeny verzií sa zapisujú do `CHANGELOG.md`
- build ZIP sa vytvára skriptom `scripts/build-plugin.sh`
- kontrola konzistencie verzie prebieha skriptom `scripts/verify-version-consistency.php`
- release workflow je v `.github/workflows/release.yml`
- automatický PR pre release zmeny je v `.github/workflows/auto-pr-version.yml`

## Inštalácia

1. Nahrajte plugin do `wp-content/plugins/ar-design-dpd`.
2. Aktivujte plugin vo WordPress administrácii.
3. Nastavte DPD údaje vo WooCommerce doprave.
4. Overte export objednávky a generovanie štítku.

## Credits

- **AR Design** – aktuálny správca a release owner forku
- **Webikon** – pôvodná integračná báza, uvedená v projekte ako coworker foundation
