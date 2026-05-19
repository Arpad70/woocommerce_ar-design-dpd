# Changelog

## 8.6.7 - 2026-05-19
- Fixed the manual `Import STATUSDATA now` action in the DPD order metabox by routing it through a dedicated admin endpoint instead of a brittle nested form submit.
- Added safer post-import redirects for both classic WooCommerce order edit screens and HPOS order screens.

## 8.6.6 - 2026-05-18
- Promoted the DPD standalone release to supersede `8.6.5` with the same ParcelShop COD metadata and repair improvements.

## 8.6.5 - 2026-05-18
- Persisted DPD ParcelShop COD and card capability metadata from checkout/session data so new COD parcelshop orders no longer export stale empty capability flags.
- Added a ParcelShop capability backfill path for existing COD orders, including an admin order-detail repair button and a CLI helper for manual repair.

## 8.6.4 - 2026-05-18
- Switched the DPD shipper export endpoint back to `https://api.dpd.sk/shipment/json` after production verification against the original `wc-dpd` plugin.
- Fixed the documented shipper export endpoint in maintainer and developer docs to match the working API target.

## 8.6.3 - 2026-05-15
- Added energy surcharge settings, monitoring helpers and shipment surcharge handling.
- Published follow-up release to align local shipping changes with the GitHub release state.

## 8.6.1 - 2026-05-12
- Stopped the DPD shipment summary from rendering non-DPD shipments after the carrier modules were split out.

## 8.6.0 - 2026-05-12
- Added `DEVELOPER_GUIDE.md` with the current plugin architecture, active flows and maintenance guidance for future developers.
- Added `MAINTAINER_HANDOFF.md` with a maintainer checklist for deployment, smoke testing and incident triage.
- Updated `README.md` and `readme.txt` to reflect the current DPD SK shipper export + STATUSDATA tracking architecture.
- Removed non-DPD carrier automation from the DPD plugin. Those carrier integrations now live in their own dedicated fix modules.

## 8.5.0 - 2026-05-06
- Initial AR Design takeover fork based on `wc-dpd` 8.5.0 by Webikon.
- Prepared standalone repository and release workflow.
