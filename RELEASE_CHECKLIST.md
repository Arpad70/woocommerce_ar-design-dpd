# RELEASE CHECKLIST

## 1. Version consistency
- Check plugin header `Version`.
- Check runtime constant `AR_DESIGN_DPD_VERSION`.
- Check `VERSION` file value.
- All three must match exactly (`X.Y.Z`).

## 2. Activation and fatal-free load
- Plugin activates without fatal error.
- WooCommerce shipping settings load.
- Order detail page opens.
- PHP log has no new fatals/warnings.

## 3. Core feature tests
- DPD export settings page renders.
- Single order export works.
- Bulk export works.
- Label download works.
- Parcelshop checkout selection works.

## 4. Update path
- Verify Git tag format `vX.Y.Z`.
- Verify GitHub release exists.
- Verify ZIP asset `ar-design-dpd.zip` exists.
- Verify WP update detection works.

## 5. Rollback readiness
- Keep previous stable ZIP available.
- Keep previous plugin folder backup available.
- Document rollback target release.

## 6. Sign-off
- Record tested environment.
- Record tester name and timestamp.
- Confirm release approved for rollout.
