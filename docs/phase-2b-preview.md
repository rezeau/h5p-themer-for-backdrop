# Phase 2B preview implementation — historical development record

**Historical development record.** This describes the evidence and scope at the original checkpoint, not the current deployment state. See [installation](installation.md), [administration](administration.md) and the [current roadmap](roadmap.md) for present guidance.

This record preserves saved-preview, Firefox CSS-correction and unsaved-preview
implementation findings. Its original isolated baseline contained 39 files;
Step 5A produced 42. Controlled transfer subsequently completed. A schema-2
custom pinned to Mint with a saved main CTA of #3366CC was the preview test case;
it is not shipped configuration or a claim about a reader's site.

The earlier unstyled Firefox sample was associated with a blocked external Core
stylesheet under the frame meta-CSP. Source inspection found correct URLs, Mint
serialization and matching selectors. Mozilla bug 1567970 describes a matching
srcdoc/meta-CSP issue: <https://bugzilla.mozilla.org/show_bug.cgi?id=1567970#c3>.
Fixed trusted inline CSS avoided external stylesheet loading without weakening
site CSP. The user subsequently confirmed saved and live Firefox previews.

## Architecture

`h5p_themer.preview.inc` supplies one administration-only renderer:

    h5p_themer_preview($selection, $schema_version = 2, $original = FALSE, $live = FALSE)

It accepts an explicit existing selection reference, not submitted form colors,
CSS, URLs or selectors. It checks authenticated theme-administrator access and
exact H5P Core API 1.28, resolves through `h5p_themer_selection_resolve()`, and
serializes through the existing pure `h5p_themer_runtime_serialize()` function.
It never reads the global selection to decide what to preview.

The result is a render array containing a complete escaped `srcdoc` document in
an iframe. Three named inline style blocks contain, in order:

1. `h5p-themer-preview-core`: exact installed H5P variable stylesheet bytes.
2. `h5p-themer-preview-sample`: exact module-owned representative CSS bytes.
3. `h5p-themer-preview-overrides`: existing serialized `.h5p-content` overrides.

There are no external stylesheet links inside the frame. Only the scoped
representative/frame CSS remains attached normally to the host administration
document; Core's `:root` declarations remain entirely inside the frame.

The renderer reads Core CSS from the fixed application location
`BACKDROP_ROOT/<H5P-module-path>/library/styles/h5p-theme-variables.css` and sample
CSS from `__DIR__/css/h5p_themer.preview.css`. No request/configuration supplies
asset paths, URLs or CSS. The existing authenticated administrator/Core 1.28
checks remain. URL construction and base-path matching are unnecessary for the
embedded assets. The two stylesheets themselves are not changed or duplicated
into another catalog, preserving actual Core defaults and all expressions.

There are no new routes, frameworks, player instances, H5P content dependencies,
migrations or configuration objects. Custom Edit alone attaches a new parent-page
behavior, `js/h5p_themer.preview.js`; no script enters the iframe. No runtime hook or
asset publication function is called. No generated CSS is written anywhere.
Existing playback behavior is unchanged.

## Theme types and inheritance

- Core/null supplies an empty named override block. The actual installed Core
  stylesheet is embedded unchanged and supplies its baseline/default spacing. Core is
  not Daylight: for example their secondary CTA colors differ.
- Daylight, Lavender, Mint, Sunset and Dark use the existing immutable catalog
  and explicit pinned version.
- Schema 1 keeps only saved sparse overrides, preserving hex case/shorthand.
  Omitted properties continue to inherit through the actual Core cascade.
- Schema 2 resolves the exact pinned palette plus sparse overrides. Inherited
  `color-mix(...)` and `rgba(...)` values remain literal expressions. The browser
  evaluates them; this component does not derive or approximate colors.
- The existing 33-property allowlist and serializer remain authoritative.
  `--h5p-theme-contrast-cta-dark` is not emitted; its catalog value remains
  inspectable in the existing exact-values table.
- Missing references, invalid definitions, unknown versions, unsafe asset paths
  and incompatible H5P produce a diagnostic without a substitute preview.

The renderer defaults to effective configuration, as playback does. Custom Edit
passes `$original = TRUE` and explains that it shows the current stored
definition with settings.php overrides excluded. Existing edit protections for
overridden configuration remain in place. Form rebuilds re-read saved data for
the server-rendered iframe and trusted metadata. After JavaScript attaches,
currently valid draft fields may update the ephemeral preview; invalid rebuilt
fields start from the saved color. Without JavaScript only saved data is shown.

## Administration integration

Built-in View pages include a saved preview and retain their exact-value table
and View/Duplicate-only model. Existing custom Edit forms include a clearly
labeled saved preview enhanced into Live preview — unsaved colors when color
inputs differ from saved values. Add/duplicate forms do not receive this behavior.
The Global Default form is unchanged; no custom View route is introduced.

Each non-Core preview includes an initially collapsed native fieldset with a
separate Core preview, rendered by the same function. Without JavaScript,
Backdrop leaves the fieldset contents accessible. Each frame has a distinct
descriptive title; it needs no JavaScript for its initial rendering.

Existing General/color fieldsets, submitted field names, validation, native
picker behavior, permission/CSRF/concurrency protections and save handlers are
unchanged. Preview changes remain memory/DOM-only; normal explicit Save is the
only persistence mechanism for custom-theme edits.

## Representative appearance and accessibility

Original module-owned HTML/CSS demonstrates primary and secondary navigation
buttons, background/surface, selected answer, heading/body/secondary/muted text,
three border colors, correct/incorrect feedback and a progress badge. Bindings
follow inspected Core 1.28, installed H5P.Components 1.0.99 and True/False 1.8.24
styles where practical. No upstream UI code, stylesheet or new dependency is
copied into the module. Existing preset attributions remain unchanged.

This is a representative preview, not an H5P activity, screenshot or promise of
identical library rendering. It uses system UI fonts and simple sample geometry;
it does not load H5P font/icon assets. Labels explicitly explain that buttons are
examples with no actions. Correct/incorrect feedback includes text, not color
alone. The static progressbar has an accessible name and numeric values. Real
buttons support normal keyboard focus and CSS hover/pressed/focus states.

The sample grid becomes one column below 40rem. Frames are 100% width with a
40rem height and normal internal scrolling; no script resizes them or intercepts
keyboard navigation. Host settings remain in their native layout, followed by
the saved preview. Verify scroll behavior and zoom manually.

Stored density is not applied to the frame. Core's natural baseline is retained;
no `h5p-medium`/`h5p-large` class or spacing override is selected from stored data.
Preview does not certify contrast or library-wide accessibility.

## Security and data integrity

- The exact sandbox is `allow-same-origin`, without script, form, popup or
  top-navigation permissions. The parent changes only the dedicated theme
  override style block and accessible preview status/title. Do not add `allow-scripts`.
- Frame content contains no script, form, input, navigation link or executable
  handler. Example buttons are `type="button"` and have no names or handlers.
- The existing frame CSP is unchanged: `default-src 'none'; style-src 'self'
  'unsafe-inline'; form-action 'none'; base-uri 'none'`. Its already-permitted
  inline styles now contain the trusted application CSS and validated overrides.
  No network-based stylesheet match against `'self'` is needed. No fonts,
  images, media, tracking, storage APIs, player initialization or xAPI run.
- Labels are escaped within the document and independently in outer attributes.
  Native `backdrop_attributes()` escapes the complete srcdoc a second time.
- Only fixed application paths supply raw CSS bytes. The H5P module-relative
  path is validated; remote-looking paths and traversal are refused. The sample
  path is a fixed suffix of this module directory. Missing, unreadable, empty,
  invalid-UTF-8 or NUL-containing assets fail with a diagnostic and no partial
  frame/Core fallback. Case-insensitive `</style` sequences are rejected even
  inside CSS comments, with unusual delimiters or at end of file.
- Raw CSS is never HTML-escaped or rewritten inside its style block. Quotes,
  ampersands, expressions and line endings stay exact in constructed srcdoc.
  The completed document is escaped at the outer attribute boundary as before.
- Strict custom hex validation and exact catalog-expression provenance are
  retained. Labels/IDs never become selectors or CSS values.
- No configuration saves, default changes, authored-content operations, runtime
  stylesheet publication or cache invalidation occur in the preview renderer.
  Existing explicit Save actions retain their previous behavior.

Parent CSP policies still apply and cannot be relaxed by this component. A parent
policy that disallows inline styles can still block them: this correction does
not bypass such a policy or claim universal CSP compatibility. Firefox computed
styles, console behavior and isolation need qualification. If unavailable, the
diagnostic/existing palette table remains available. The sandbox is defense in
depth, not a substitute for escaping.

## Automated verification

`tests/preview.php` runs after the existing full suite using memory Config/cache
collaborators and the real Backdrop attributes/base-path helper bodies. It
decodes the actual rendered srcdoc, compares CSS against independent preset
fixtures, checks both schema types, Core comparison, stored/effective overrides,
native Form API validation/rebuild behavior, error/access paths, injection
rejection and no-public-storage/cache/configuration side effects. The harness
throws on database access. Existing runtime tests use private temporary storage
only. No site settings/bootstrap or live database is used.

Step 4B adds exact embedded-byte/order checks and removes assumptions about
iframe asset URLs. Missing/unreadable files and unsafe bytes are exercised for
both fixed asset paths through a private process-local file-wrapper double.
The real filesystem wrapper is restored in `finally`; no real stylesheet is
renamed, removed or edited, and fixture CSS lives only in memory. Tests require
a diagnostic without any partial frame and unchanged service snapshots for
each failure. The existing density assertion checks applied HTML classes, not
class names naturally present in the now-embedded unchanged Core stylesheet.

For the historical Step 4B baseline, PHP 7.4.33 and 8.4.22 ran with `-n tests/run.php`, supplying source-only
`--backdrop-root` and `--h5p-root`. Run Node.js 22.23.1 with
`node --test --test-reporter=spec tests/js/admin-colors.test.cjs`. At that checkpoint Node
tests were unchanged; Step 5A below adds the live-preview behavior and tests.
Current Node commands require BACKDROP_SOURCE_ROOT; see the README.

Tests assert markup and CSS contracts; they do not simulate browser layout,
computed color expressions, iframe security enforcement or assistive technology.

Step 4B regression baseline on 2026-10-16 (before the Step 5A additions):

| Runtime | Passed | Failed |
| --- | ---: | ---: |
| PHP 7.4.33 | 1,497 | 0 |
| PHP 8.4.22 | 1,497 | 0 |
| Node.js 22.23.1 | 35 | 0 |

The PHP total is 60 above the 1,437-check saved-preview baseline, with focused
updates to the prior URL/style-block/density assertions. Modified PHP syntax
passes on both versions. Node remains the unchanged native-picker regression
suite. Source/implementation
SHA-256 inventories and isolation verification are stored alongside this
timestamped working directory, outside the module inventory.

## Controlled Firefox checkpoints after separate transfer authorization

1. Establish baseline configuration/content and retain a verified safety copy.
   Verify the allowlisted deployment first. No new route/menu rebuild is needed.
2. Open each existing versioned built-in View page. Verify saved identity, sample
   controls/states/feedback, and the unchanged exact-value table.
3. Open Core comparison; confirm original Core defaults, not Daylight. Compare
   Mint main CTA `#187571` against the already qualified H5P playback activity
   without changing the global default. Confirm admin chrome is unaffected.
4. Inspect the existing Copy of Mint in Edit. Initial saved CTA must be `#3366CC`.
   Without saving, try `#B03060`, shorthand and several simultaneous edits. The
   custom preview should update; its Core comparison must not. Blank restores
   pinned Mint `#187571`; incomplete `#12` retains the field's last valid preview
   color and shows a warning. Revert to `#3366CC`, then leave without saving.
   Under separate fixture authorization, repeat with a schema-1 sparse custom:
   clearing must restore the Core cascade without stale declarations.
5. Inspect network/console: no external stylesheet requests from either srcdoc
   frame and no corresponding CSP violations. Normal host preview CSS loading
   is retained. Verify no frame scripts, activity loads, runtime asset generation
   or unexpected requests/errors. A stricter inherited parent policy remains
   enforced; do not weaken site CSP if it reports a separate inline restriction.
6. Disable JavaScript and reload. Verify both saved previews and Core comparison
   remain accessible, as do original editable hex fields and native fieldsets.
7. Check narrow windows, 200%/400% zoom, internal scrolling, keyboard entry/exit,
   focus outlines, frame titles, headings, feedback labels and progress semantics.
   Use a screen reader where available. Verify the inherited expressions compute
   correctly and the host document's Core variables are unaffected.
8. Recheck unchanged global default, configuration and content. Browser viewing
   is not authorization to save themes or edit activities.

Rollback of a future controlled transfer should restore only its reviewed file
allowlist from its verified safety copy, under separate authorization. This
preview introduces no configuration migration or data requiring restoration.

## Unsaved custom colors — isolated Step 5A

The existing renderer accepts a fourth internal `$live` flag, enabled only by
custom Edit. It exports the existing canonical 33-property allowlist, validated
sparse saved hex values, and the exact pinned inheritance map as escaped JSON
metadata on the parent section. The catalog-only unsupported property is excluded.
Core/built-in/ordinary saved previews have no live behavior.

The parent behavior listens to native text `input`/`change` events, including
changes dispatched by the existing native color-picker companion. A complete
`#RGB`/`#RRGGBB` updates immediately without trimming, expanding or rewriting the
authoritative field. No AJAX, fetch, form submission, config API, content API,
runtime publisher or cache service is used. Save and server validation remain
authoritative. Labels and density are not part of this color-only preview.

Each update combines all currently valid fields with each invalid field's last
valid value. Invalid/incomplete strings are never serialized; an adjacent hidden-
until-needed description and a polite status explain the retained color. Heading
and iframe title distinguish unsaved from saved colors. When all original color
strings are restored, the saved appearance and label return.

Blank means inheritance. Schema 1 omits the declaration so the actual embedded
Core cascade supplies its value. Schema 2 restores the exact pinned value,
including literal `color-mix(...)`/`rgba(...)`; the browser evaluates it against
the current palette. No colors are approximated or derived. Only allowlisted
properties appear in a fixed `.h5p-content` rule in the dedicated override style
block. The existing Core/sample CSS blocks, srcdoc, CSP, sandbox and Core
comparison remain unchanged. The document is never regenerated for a keystroke.

The existing native Form API reset, when invoked, is observed after its default
action; remembered preview values are first reset to saved values and current
valid form defaults are reapplied. This removes stale properties even when a
rebuilt form has an invalid default. A cancelled reset is respected. This step
adds no new reset control. The behavior also waits for/retries on the frame load
event, respects disabled/read-only inputs and handles unavailable frame access
with a status. Repeated/partial Backdrop attachments do not duplicate handlers.

Metadata contains trusted server-resolved inheritance, never field-supplied
expressions. Client validation rejects structural CSS/HTML in metadata, extra
properties, malformed maps and injected hex input. DOM text and styles use
`textContent`, not HTML insertion. No input-derived selector or URL is constructed.
The script adds descriptions without replacing existing picker descriptions or
server error semantics. The existing responsive layout remains; real narrow-screen,
keyboard, screen-reader and Firefox CSP behavior still require manual qualification.

`tests/preview-live.php` checks the metadata, both schemas, saved `#3366CC`, native
draft validation/rebuilds and zero side effects with memory services. Node runs
the actual production behavior against DOM/event doubles, including native picker
integration, pending frame loads, per-field invalid retention, multiple edits,
clearing, expression restoration, reverting/reset, blocked controls, bad metadata,
and unchanged Core comparison/CSS/srcdoc. These are behavior checks, not a browser
emulator or a substitute for Firefox verification.

Set BACKDROP_SOURCE_ROOT as described in the README, then run all client suites:

    node --test --test-reporter=spec tests/js/admin-colors.test.cjs tests/js/preview-live.test.cjs

Firefox after separate controlled-transfer authorization must confirm immediate
picker/text updates, invalid messages, inherited expressions, saved/unsaved labels,
reverting, keyboard/reset behavior and narrow/zoomed layout. With JavaScript
disabled, reload must show saved `#3366CC` and normal PHP validation. Re-read
stored config after unsaved edits: CTA must still be `#3366CC`, Core/null still
global, and content unchanged. No live-site HTTP or browser tests are performed
as part of this isolated implementation.

Final isolated Step 5A verification on 2026-10-16:

| Runtime | Passed | Failed |
| --- | ---: | ---: |
| PHP 7.4.33 | 1,554 | 0 |
| PHP 8.4.22 | 1,554 | 0 |
| Node.js 22.23.1 | 80 | 0 |

This retains the saved-preview baseline with focused asset-attachment assertions
updated for Edit, adds 57 PHP checks and 45 client behavior tests, and keeps the
35 native-picker tests. Five changed/new PHP files pass syntax checks on both
versions; all three changed/new JavaScript/test files pass Node syntax checks.
Subsequent controlled transfer completed. The user confirmed Firefox PASS for
saved previews, live edits, invalid input, inheritance/reset, multiple overrides,
native picker synchronization and JavaScript-disabled fallback. A 375px viewport
was usable but cramped. These are user-performed manual results, not agent-run
browser tests or comprehensive browser/accessibility certification.
