# Release Process

## Samostatné verzovanie

1. uprav `VERSION`
2. uprav verzii v `ar-design-dpd.php`
3. uprav `AR_DESIGN_DPD_VERSION`
4. doplň záznam do `CHANGELOG.md`
5. merge PR do `main`

Kontrola konzistencie:

```bash
php scripts/verify-version-consistency.php
```

Po merge do `main` sa automaticky spustí workflow `.github/workflows/release.yml`.
Ak sa v commite zmenil súbor `VERSION`, workflow:

- overí syntax PHP súborov,
- overí konzistenciu verzie,
- vytvorí tag `v<version>`,
- vytvorí GitHub Release,
- priloží asset `ar-design-dpd.zip`.

## Lokálny build ZIP balíka (voliteľné)

```bash
bash scripts/build-plugin.sh
```

Výstup lokálne:

- ZIP sa vytvorí do `build/`
- názov súboru bude `ar-design-dpd-<version>.zip`

## Git workflow

### Varianta A: branch + PR

1. Vytvor branch, napr. `release/8.6.1`.
2. Commitni release súbory (`VERSION`, `CHANGELOG.md`, `ar-design-dpd.php`) + súvisiace zmeny.
3. Pushni branch na GitHub.
4. `auto-pr-version.yml` má vytvoriť PR automaticky.
5. Po schválení mergni PR do `main`.

### Varianta B: priamy push do `main`

Použi len ak je to interne schválené.

## Produkčný update

1. zazálohuj databázu
2. zazálohuj adresár `wp-content/plugins/ar-design-dpd`
3. nech WordPress detegovať novú verziu pluginu z GitHub release
4. spusť štandardnú aktualizáciu pluginu v administrácii
5. over export objednávok, štítky a DPD nastavenia
