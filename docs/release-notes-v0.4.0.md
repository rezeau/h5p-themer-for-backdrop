<a id="h5p-themer-for-backdrop-v040--release-candidate"></a>

# H5P Themer for Backdrop v0.4.0

SPDX-License-Identifier: GPL-3.0-or-later.

Release notes for v0.4.0, based on the v0.2.1 stable baseline. v0.3.0 was withdrawn.
These notes do not assert that a tag or public release has been published.

## Changes since v0.2.1

- Optional native Density for qualified top-level **H5P.TrueFalse 1.8.24 iframe
  players on H5P Core API 1.28**. Small is compact, Medium intermediate and Large
  spacious; these profiles do not set container width or promise three distinct
  text sizes.
- Custom themes apply their saved native profile. Core and built-in themes restore
  each qualified player's original Density. Site default follows the effective
  Global Default, including a hidden custom default, or restores original when no
  override exists.
- Server-side main-library qualification and browser-side ownership/readiness checks
  limit changes to verified players. Other types/versions, nested players, DIV and
  external playback, development libraries and ambiguous embeddings retain original
  Density. Distinct qualified players retain independent lifecycle/original state;
  duplicate embeddings of the same content ID are excluded.
- Effective changes use native Density classes and targeted native resize handling.
  Activity instances are not recreated. Answers, feedback and Retry remain usable
  during theme changes; navigation/reload does not acquire answer persistence.
- Administration/help text and upgrade/cache instructions now explain activation,
  exact support, original-profile restoration and color-only previews.

Existing colors, the learner theme picker and its tab-session preference mechanism
retain their own compatibility rules. Saved themes, schema-1/2 formats, preset
pins and configuration defaults are unchanged. No H5P Core/library edits, custom
Density CSS or private spacing variables are introduced.

## Activation and upgrade

Native Density is **disabled by default**. Only explicit boolean TRUE enables it:

```php
$settings['h5p_themer_native_density_enabled'] = TRUE;
```

Strings, integer 1, arrays, NULL and FALSE do not enable it. This is an installation
setting, not a saved-theme field. Unsupported players retain original Density even
when the setting is TRUE.

For v0.2.1 installations already at schema 1003, there is no new database update or
configuration migration. Back up complete code/settings/configuration/database
recovery state; deploy the module coherently without uninstalling. In a guarded,
fully bootstrapped context for the intended site, refresh hook discovery with
`module_implements_reset();` and rebuild with `backdrop_flush_all_caches();`.
No-argument `cache_clear_all()` alone does not discover the new hook. Follow the
[complete upgrade/activation/disable/rollback instructions](installation.md#v021-to-v040-development-upgrade-and-rollback).

Setting changes require cache refresh and fresh playback. Existing open pages keep
their supplied metadata until reload. Disabling the Density setting and disabling
the whole module are separate operations; neither requires uninstalling.
Full code rollback was not rehearsed; recovery guidance is documented.

## Accepted verification

| Evidence | Accepted result |
| --- | --- |
| Complete PHP baseline, PHP 7.4.33 / 8.3.18 / 8.4.22 | 3,793 assertions on each version, zero failures |
| Complete Node.js 22.23.1 baseline | 470 tests, zero failures |
| Checkpoint 3C Lite | 46 focused assertions, zero failures; all seven manual Firefox acceptance tests passed |
| Separate Backdrop module lifecycle verification | 85 focused checks, zero failures |

The accepted Lite scope supersedes the full Stage 2/3 plan. It exercised a warmed
v0.2.1 upgrade, hook/cache refresh, aggregate delivery and Density-setting OFF/ON.
The separate lifecycle clone exercised actual module disable/re-enable through
Backdrop's normal module administration, with the Density opt-in remaining TRUE.
No uninstall was performed. Schema 1003, all eight saved configuration hashes and
the fixture Global Default `fresh_source` were preserved. Hooks, colors, the picker,
qualified Density and playback returned; the unsupported control kept original
Density. Accepted reference environments remained unchanged.

These counts describe accepted evidence, not another complete regression run during
release preparation. See [readiness and evidence boundaries](v0.4.0-readiness.md).

## Compatibility, limitations and deferred work

Backdrop 1.x is declared; actual inspection/verification used **Backdrop 1.35.2**.
The enabled H5P integration must expose **Core API exactly 1.28**; older/newer APIs
and arbitrary integration releases are not qualified. Native Density support is
restricted to the exact TrueFalse version and playback mode above. Wider browser,
keyboard/screen-reader, RTL/mobile and library support is not established; no WCAG
certification is claimed.

Independent learner Density buttons and overrides are deferred to **v0.4.1**.
Live Density Preview is deferred unless separately authorized. Saved and live
unsaved-color previews remain representative color-only samples, not running H5P
activities. No deferred feature is part of v0.4.0.

The curated production package contains runtime files, documentation and the
existing license/attribution notices. Tests, developer harnesses, private artifacts,
temporary environments, settings/credentials, SQL, generated files and backups are
excluded. Curated archives use the reviewed allowlist and require inventory,
SHA-256 and integrity verification. Package verification does not publish a release.
