# Changelog

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
