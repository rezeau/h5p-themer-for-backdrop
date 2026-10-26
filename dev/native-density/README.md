# Native Density — v0.4.0 Checkpoint 1

This removable developer proof of concept is not attached by the Backdrop module.
It changes only a specifically identified top-level True/False player's native
Density class. Production code, version metadata, theme colors and persistent
configuration remain at the restored v0.2.1 baseline.

## Disposable environment

The running test page is **http://127.0.0.1:8767/**. It is a standalone native H5P
runtime, not a cloned Backdrop site. No site bootstrap, settings.php, database,
account, content record, or network scoring endpoint is used. The loopback server
accepts GET/HEAD only and serves the disposable fixture directory. A second
True/False player is the unchanged control.

The fixture copies public Core and library assets from an existing installation
without writing to it. No files under `C:\wamp64\www\backdrop` or the online
installation are changed. Current asset source:
`C:\wamp64\www\backdrop-themer-v020-fresh`.

Generated copies, manifest, test logs and server logs live under
`artifacts/native-density-checkpoint1/` (ignored by Git). The manifest records
versions and verifies all copied asset bytes against their source. It excludes
site settings, authored site content and database credentials. The example
question and English interface are generated locally.

To prepare a new fixture when that output directory does not already exist:

```powershell
node dev/native-density/prepare.cjs C:/wamp64/www/backdrop-themer-v020-fresh
node dev/native-density/serve.cjs
```

Preparation intentionally refuses to overwrite an existing fixture. The server
uses port 8767; it does not take over an occupied port. The current server runs as
a hidden background process; its PID is recorded in the ignored `server.pid`.
Restart it with the second command if it is no longer running. No Apache/Wamp
configuration changes are required.

## Source evidence and design

- Backdrop H5P `_include_h5p()` in `modules/h5p/h5p.module` creates a DIV for DIV
  integrations, or an `iframe.h5p-iframe` with an explicit content ID for iframe
  integrations. True/False 1.8.24's `library.json` declares iframe embedding.
- Core `library/js/h5p.js` writes the iframe document and its `.h5p-content` root,
  loads the existing assets and initializes the native activity. Core adds
  `h5p-<theme.density>`, defaulting to `h5p-large`.
- Core `library/styles/h5p-theme-variables.css` maps Density classes on that root
  to native spacing, font and scaling aliases. An unclassified root uses Small
  aliases; `h5p-small` explicitly selects that fallback. Medium and Large have
  more-specific native selectors. No stylesheet is modified or substituted.
- At a 16px root size: Small/Medium/Large use spacing-s values
  0.64rem / 0.8rem / 1rem and native scaling 0.6 / 0.8 / 1.
  Small and Medium intentionally share font sizes (90% of Large). Three distinct
  text sizes are not promised by Core.
- The existing Themer color system serializes global colors to a stylesheet and,
  when enabled, scopes learner palettes using HTML selection and eligibility
  attributes. None of those functions, scripts, styles or settings is changed.
  The proof uses native default colors and does not implement theme selection.
- `harness.js` binds only `#density-target`, content ID 101, after native
  initialization. It finds exactly one root and one instance by that ID in that
  iframe's own realm. Control ID 102 is read only. No registry-position lookup,
  polling, nested-player support, or automatic discovery feature is added.
- `controller.js` removes the three competing Density classes and adds exactly
  one requested native class. All other classes are preserved. Restore returns
  to the original effective profile (Large in this fixture); an originally
  unclassified root can return to its native fallback.
- Changes coalesce into at most one animation-frame job. If measured root height
  changed, it invokes `H5P.trigger(theExplicitInstance, 'resize')`. Core owns iframe
  sizing. No player recreation, answer reset, custom spacing, private Density
  variables, iframe-height assignment or global resize dispatcher is introduced.
  Removed/replaced targets are rejected and pending work can be cancelled.

## Automated checks

Focused contract tests:

```powershell
node --test --test-reporter=spec tests/js/native-density.test.cjs
```

They cover all three profiles, competing-class removal, unrelated-class and
player preservation, cycling/restoration, invalid targets/values, bounded resize,
coalescing, net-zero transitions, removal, document replacement and disposal.
DOM doubles verify these contracts; real computed layout is checked separately.
Existing regression commands and requirements remain in the repository README.

Qualified runtime assets: Core API 1.28, True/False 1.8.24, Question 1.5.56,
JoubelUI 1.3.46, Components 1.0.99. Other library versions are not qualified here.

## Manual Firefox checkpoint — required

1. Open **http://127.0.0.1:8767/** in Firefox on this computer. Use 100% zoom and a
   consistent window width. Wait for “Ready” and two visible, working players.
   The original profile is Large. If the page cannot load, stop; do not use an
   existing Backdrop site as a substitute.
2. Click **Refresh measurements**. Record the Large baseline. The target and
   control should show `h5p-large`. The measurement table includes question/answer
   fonts, answer padding, button/evaluation sizes, activity dimensions, iframe
   height and content-fit status. Measurements are snapshots; refresh after each
   change once native layout has settled.
3. Select **Small / Compact**. Observe question/answer spacing, text, answer and
   Check controls, and total activity size. Refresh measurements. Only the target
   should say `h5p-small`; the control must remain Large and visually unchanged.
4. In the target choose **False**, then **Check**. Confirm incorrect feedback and
   score 0. Refresh measurements and inspect feedback/evaluation layout and height.
5. With this feedback still displayed, switch **Medium**, then **Large / Wide**,
   refreshing measurements after each. Spacing, control sizes and total bounds
   should change visibly. The chosen False answer, feedback and score must remain.
   Small and Medium fonts can be equal; their spacing and scaling must differ.
6. Cycle **Small → Medium → Large → Small** again without reloading. Confirm exactly
   one native class in the table, readable feedback, working controls and no
   clipping or horizontal overflow. Keep the control player untouched; compare
   its class, fonts, padding and dimensions throughout.
7. Click **Restore original profile**, then refresh measurements. The target must
   return to `h5p-large` while retaining its answer and feedback. It need not have
   the same height as the unanswered control because it is showing feedback.
8. Use the target's **Retry**, choose **True**, and click **Check**. Confirm correct
   feedback and score 1. Switch all profiles again and confirm this state remains.
   If Retry is not offered after a correct answer, reload the disposable page to
   start another test; reload intentionally starts fresh unsaved activities.
9. Answer/check the control separately to confirm it is functional. Then change
   only target Density: the control's own answer, feedback, class and dimensions
   must stay unchanged. Compare geometry only while each player's own state is
   held constant.
10. Report **Firefox PASS or FAIL**, Firefox version, window width/zoom, and any
    profile where visual differences, state retention, control isolation, or
    clipping fail. Include measurement values or a screenshot for failures.

This is not full Backdrop integration, a full learner selector, a persistent
theme feature, nested H5P support, or a browser/library compatibility guarantee.
Firefox acceptance is pending. Stop here until the user's explicit PASS/FAIL;
do not start Checkpoint 2, commit, push, tag, release, or deploy to a site.
