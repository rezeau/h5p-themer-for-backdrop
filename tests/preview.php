<?php
/**
 * @file
 * Saved previews with real resolution/serialization and memory-only services.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */

/** Decodes the actual attribute produced by native backdrop_attributes(). */
function preview_document(array $build) {
  if (!isset($build['sample']['#markup']) || !preg_match('/\bsrcdoc="([^"]*)"/', $build['sample']['#markup'], $match)) {
    throw new RuntimeException('Expected a rendered preview frame.');
  }
  return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

function preview_style_block(array $build, $name) {
  $document = preview_document($build);
  if (!preg_match('~<style id="h5p-themer-preview-' . preg_quote($name, '~') . '">(.*?)</style>~s', $document, $match)) { throw new RuntimeException('Expected named preview CSS block.'); }
  return $match[1];
}

function preview_css(array $build) {
  return preview_style_block($build, 'overrides');
}

function preview_services_snapshot() {
  return array(H5PThemerMemoryStorage::$raw, H5PThemerMemoryStorage::$writes,
    $GLOBALS['runtime_cache_invalidations'], $GLOBALS['runtime_wrapper_calls']);
}

admin_reset(); admin_publication_fixture();
$version = h5p_themer_preset_versions()[0];
$catalog_before = h5p_themer_presets($version)['data'];
$routes_before = h5p_themer_menu();
$before = preview_services_snapshot();
$core = h5p_themer_preview(NULL);
$core_doc = preview_document($core);
check(preview_css($core) === '', 'Core preview emits an empty override block');
check(!isset($core['core_comparison']), 'Core does not recursively compare itself');
check(strpos($core_doc, '<h2>H5P Core defaults</h2>') !== FALSE, 'Core retains its separate identity');
check(preview_style_block($core, 'core') === file_get_contents($h5p_root . '/library/styles/h5p-theme-variables.css'), 'Exact installed Core variable bytes are embedded inside srcdoc');
check(preview_style_block($core, 'sample') === file_get_contents(dirname(__DIR__) . '/css/h5p_themer.preview.css'), 'Exact module-owned representative CSS bytes are embedded inside srcdoc');
check(preg_match('~<link\b~i', $core_doc) === 0, 'No external stylesheet links remain inside srcdoc');
check($core['#attached']['css'] === array('modules/h5p_themer/css/h5p_themer.preview.css') && !isset($core['#attached']['js']), 'Host receives only the scoped local preview CSS');
check(strpos($core_doc, '<style id="h5p-themer-preview-core">') < strpos($core_doc, '<style id="h5p-themer-preview-sample">') && strpos($core_doc, '<style id="h5p-themer-preview-sample">') < strpos($core_doc, '<style id="h5p-themer-preview-overrides">') && strpos($core_doc, '<style id="h5p-themer-preview-overrides">') < strpos($core_doc, '<style id="h5p-themer-preview-density">') && substr_count($core_doc, '<style ') === 4, 'Four ordered blocks separate density from live colors');
check(preg_match('/sandbox="allow-same-origin"/', $core['sample']['#markup']) === 1, 'Exact required iframe sandbox');
check(strpos($core['sample']['#markup'], 'title="Saved theme preview: H5P Core defaults"') !== FALSE, 'Descriptive frame title');
check(strpos($core['sample']['#markup'], 'srcdoc="&lt;!doctype html&gt;') !== FALSE, 'Complete document is escaped at the attribute boundary');
check(strpos($core_doc, "default-src 'none'; style-src 'self' 'unsafe-inline'; form-action 'none'; base-uri 'none'") === FALSE && strpos($core_doc, 'Content-Security-Policy') !== FALSE, 'CSP attribute is HTML-escaped inside the document');
check(strpos(html_entity_decode($core_doc, ENT_QUOTES, 'UTF-8'), "default-src 'none'; style-src 'self' 'unsafe-inline'; form-action 'none'; base-uri 'none'") !== FALSE, 'CSP restricts scripts, requests, submissions and base URLs');
foreach (array('script', 'form', 'a', 'input', 'textarea', 'object', 'embed', 'base') as $tag) {
  check(preg_match('~<' . $tag . '\b~i', $core_doc) === 0, 'No executable/submitting/navigating element: ' . $tag);
}
check(preg_match('/\bon[a-z]+\s*=/i', $core_doc) === 0 && strpos($core_doc, 'javascript:') === FALSE, 'No inline script handlers or JavaScript URLs');
check(substr_count($core_doc, '<button type="button"') === 2, 'Both inert sample buttons remain keyboard focusable without submission');
foreach (array('<main ', '<h2>', '<h3>', 'Correct:', 'Incorrect:', 'Secondary text', 'Muted text', 'Selected answer (example)', 'Preview only.', 'Border 1', 'Border 2', 'Border 3', 'role="progressbar"', 'aria-label="Example progress"', 'aria-valuemin="0"', 'aria-valuemax="5"', 'aria-valuenow="2"') as $semantic) {
  check(strpos($core_doc, $semantic) !== FALSE, 'Semantic preview example: ' . $semantic);
}
$core_source = file_get_contents($h5p_root . '/library/styles/h5p-theme-variables.css');
check(strpos($core_source, '--h5p-theme-secondary-cta-base: #E3E9F1;') !== FALSE && $catalog_before['daylight']['colors']['--h5p-theme-secondary-cta-base'] === '#202122', 'Real Core baseline is not replaced with Daylight');
check(preview_services_snapshot() === $before, 'Core preview makes no config/cache/public-storage/content calls');

foreach (array('daylight', 'lavender', 'mint', 'sunset', 'dark') as $id) {
  $before = preview_services_snapshot();
  $selection = array('kind' => 'builtin', 'id' => $id, 'version' => $version);
  $preview = h5p_themer_preview($selection);
  check(preview_css($preview) === runtime_fixture_css($fixture['themes'][$id]['values']), 'Exact independent preset fixture in preview: ' . $id);
  check(preview_style_block($preview, 'core') === preview_style_block($core, 'core') && preview_style_block($preview, 'sample') === preview_style_block($core, 'sample') && preg_match('~<link\b~i', preview_document($preview)) === 0, 'Preset uses the same exact embedded assets with no stylesheet requests: ' . $id);
  check(preview_css($preview['core_comparison']['preview']) === '', 'Core comparison stays unthemed: ' . $id);
  check($preview['core_comparison']['#collapsible'] === TRUE && $preview['core_comparison']['#collapsed'] === TRUE, 'Native comparison fieldset: ' . $id);
  check(strpos(preview_css($preview), '--h5p-theme-contrast-cta-dark:') === FALSE, 'Runtime-unsupported catalog property is not demonstrated: ' . $id);
  $view = h5p_themer_preset_view($id, $version);
  check(preview_css($view['saved_preview']) === preview_css($preview), 'Existing preset View uses the same renderer: ' . $id);
  check(count($view['colors']['#rows']) === 34 && !isset($view['actions'], $view['edit'], $view['delete']), 'Exact inspection and immutability preserved: ' . $id);
  check(!isset($view['#attached']['js'], $preview['#attached']['js']), 'Saved preset preview needs no JavaScript: ' . $id);
  check(preview_services_snapshot() === $before, 'Preset and comparison make zero persistent/service writes: ' . $id);
}

$legacy = fixture_theme('preview_legacy');
$legacy['label'] = 'Stored <script>alert("x")</script> & "theme"';
$legacy['colors'] = array('--h5p-theme-main-cta-base' => '#aBc', '--h5p-theme-background' => '#Ab12Ef');
seed('h5p_themer.theme.preview_legacy', $legacy);
$pinned = fixture_pinned_theme('preview_pinned', 'mint');
$pinned['colors'] = array('--h5p-theme-main-cta-base' => '#DeF');
seed('h5p_themer.theme.preview_pinned', $pinned);
$before = preview_services_snapshot();
$legacy_preview = h5p_themer_preview('preview_legacy', 1);
$legacy_doc = preview_document($legacy_preview);
check(preview_css($legacy_preview) === ".h5p-content {\n  --h5p-theme-main-cta-base: #aBc;\n  --h5p-theme-background: #Ab12Ef;\n}\n", 'Schema 1 keeps sparse values, original case and shorthand');
check(strpos($legacy_doc, '<h2>' . check_plain($legacy['label']) . '</h2>') !== FALSE && strpos($legacy_doc, '<script>') === FALSE, 'User label is plain text in decoded srcdoc');
check(strpos($legacy_preview['sample']['#markup'], 'title="' . check_plain('Saved theme preview: ' . $legacy['label']) . '"') !== FALSE, 'User label is independently escaped in the outer title');
check(strpos($legacy_preview['sample']['#markup'], '&amp;lt;script&amp;gt;') !== FALSE, 'Inner text escaping survives the outer attribute escape');
$pinned_ref = array('kind' => 'custom', 'id' => 'preview_pinned');
$pinned_preview = h5p_themer_preview($pinned_ref);
check(preview_css($pinned_preview) === runtime_fixture_css(array_replace($fixture['themes']['mint']['values'], $pinned['colors'])), 'Schema 2 combines exact pinned palette and sparse override');
check(strpos(preview_css($pinned_preview), 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)') !== FALSE, 'Inherited color-mix stays an exact expression');
check(h5p_themer_theme_load('preview_pinned')['data'] === $pinned && h5p_themer_theme_load('preview_legacy')['data'] === $legacy, 'Preview does not migrate, flatten or normalize either schema');
check(preview_services_snapshot() === $before, 'Both schema previews and escaped labels leave every service unchanged');

// A preview has its own explicit identity, independent of the global selection.
seed('h5p_themer.settings', fixture_typed_settings(array('kind' => 'builtin', 'id' => 'dark', 'version' => $version)));
$before = preview_services_snapshot();
check(preview_css(h5p_themer_preview($pinned_ref)) === preview_css($pinned_preview) && preview_css(h5p_themer_preview(NULL)) === '', 'Selected global Dark cannot contaminate explicit custom or Core preview');
check(preview_services_snapshot() === $before, 'Selected global default is untouched by comparisons');

// Editing previews stored data. A separately requested effective preview can
// show environment overrides, matching the resolver contract used by playback.
$GLOBALS['config']['h5p_themer.theme.preview_legacy'] = array('label' => 'Environment label', 'colors' => array('--h5p-theme-background' => '#123456'));
$before = preview_services_snapshot();
$effective = h5p_themer_preview('preview_legacy', 1);
$stored = h5p_themer_preview('preview_legacy', 1, TRUE);
check(strpos(preview_document($effective), '<h2>Environment label</h2>') !== FALSE && strpos(preview_css($effective), '#123456') !== FALSE, 'Effective previews respect validated environment overrides');
check(preview_css($stored) === preview_css($legacy_preview) && isset($stored['stored_notice']), 'Explicit stored mode excludes environment overrides and explains it');
check(preview_services_snapshot() === $before, 'Both override modes are read-only');
$GLOBALS['config'] = array();

foreach (array('preview_legacy', 'preview_pinned') as $id) {
  $before = preview_services_snapshot();
  $bundle = admin_form('theme', 'edit', $id);
  $edit = $bundle[1];
  check(preview_css($edit['saved_preview']) === preview_css(h5p_themer_preview(array('kind' => 'custom', 'id' => $id), 2, TRUE)), 'Edit uses the shared stored renderer: ' . $id);
  check($edit['general']['#collapsed'] === FALSE && $edit['colors']['main']['#collapsed'] === TRUE && $edit['general']['id']['#disabled'] === TRUE, 'Existing grouping and identity behavior preserved: ' . $id);
  check($edit['#attached']['js'] === array('modules/h5p_themer/js/h5p_themer.admin.js') && $edit['saved_preview']['#attached']['js'] === array('modules/h5p_themer/js/h5p_themer.preview.js'), 'Native picker and parent-only live-preview assets stay separately attached: ' . $id);
  $input = array('id' => $id, 'label' => 'Unsaved name', 'description' => '', 'density' => 'large', 'colors' => array('--h5p-theme-main-cta-base' => '#123456'));
  $result = admin_post($bundle, $input, TRUE, FALSE);
  check(!$result['errors'] && $result['form']['colors']['main']['--h5p-theme-main-cta-base']['#value'] === '#123456', 'Native form retains unsaved submitted color: ' . $id);
  check(preview_css($result['form']['saved_preview']) === preview_css($edit['saved_preview']), 'Submitted valid draft does not alter saved preview: ' . $id);
  $state = $result['state']; $state['values'] = array();
  $rebuilt = h5p_themer_theme_form(array(), $state);
  check(preview_css($rebuilt['saved_preview']) === preview_css($edit['saved_preview']), 'Rebuilt form still resolves current saved data: ' . $id);
  check(preview_services_snapshot() === $before, 'Validation/rebuild without submit cannot persist or publish: ' . $id);
}
foreach (array(admin_form('theme'), admin_form('theme', 'duplicate', 'preview_legacy'), admin_form('theme', 'preset_duplicate', 'mint', $version)) as $bundle) {
  check(!isset($bundle[1]['saved_preview']), 'No misleading saved preview for an unsaved/new/duplicate theme');
}
check(!isset(admin_form('default')[1]['saved_preview']) && h5p_themer_menu() === $routes_before, 'No global-default behavior or route changes');

foreach (array(array('kind' => 'custom', 'id' => 'missing'), array('kind' => 'builtin', 'id' => 'unknown', 'version' => $version), array('kind' => 'builtin', 'id' => 'mint', 'version' => 'latest'), array('kind' => 'core'), array('kind' => 'custom', 'id' => '../bad'), array('kind' => 'custom', 'id' => 'preview_legacy', 'url' => 'https://example.invalid/'), 'core', array()) as $bad) {
  $before = preview_services_snapshot(); $result = h5p_themer_preview($bad);
  check(!isset($result['sample']) && !isset($result['core_comparison']) && strpos($result['#markup'], 'No fallback') !== FALSE, 'Invalid/missing reference yields diagnostics without a fallback frame');
  check(preview_services_snapshot() === $before, 'Invalid reference leaves all services unchanged');
}
foreach (array('#12', "#abc\n", ' #abc', 'red', '#fff; color:red', 'url(https://example.invalid)', '</style><script>alert(1)</script>', 'color-mix(in srgb,red,blue)', 'rgba(1,2,3,.5)') as $color) {
  $bad = $legacy; $bad['colors'] = array('--h5p-theme-background' => $color); seed('h5p_themer.theme.preview_legacy', $bad);
  $before = preview_services_snapshot(); $result = h5p_themer_preview('preview_legacy', 1);
  check(!isset($result['sample']) && strpos($result['#markup'], 'No fallback') !== FALSE, 'Malformed or injected custom CSS is refused');
  check(preview_services_snapshot() === $before, 'Rejected color is never repaired or persisted');
}
foreach (array('body', '--h5p-theme-background; color', '--h5p-theme-contrast-cta-dark', '--h5p-theme-font-name') as $property) {
  $bad = $legacy; $bad['colors'] = array($property => '#123'); seed('h5p_themer.theme.preview_legacy', $bad);
  check(!isset(h5p_themer_preview('preview_legacy', 1)['sample']), 'Unknown selector/property cannot enter srcdoc CSS: ' . $property);
}
$bad = $pinned; $bad['colors']['--h5p-theme-contrast-cta-light'] = $catalog_before['mint']['colors']['--h5p-theme-contrast-cta-light'];
seed('h5p_themer.theme.preview_pinned', $bad);
check(!isset(h5p_themer_preview($pinned_ref)['sample']), 'Even a catalog expression cannot be supplied as a custom stored override');
$bad = $pinned; $bad['base_preset']['version'] = 'latest'; seed('h5p_themer.theme.preview_pinned', $bad);
check(!isset(h5p_themer_preview($pinned_ref)['sample']), 'Invalid pinned inheritance cannot fall back to another version');
seed('h5p_themer.theme.preview_legacy', $legacy); seed('h5p_themer.theme.preview_pinned', $pinned);

$before = preview_services_snapshot();
foreach (array('/', '/training/site/', '//example.invalid/', '/../', '/safe/../', '/x?bad/', '/x"bad/') as $base_path_value) {
  $GLOBALS['base_path'] = $base_path_value;
  $without_urls = preview_document(h5p_themer_preview(NULL));
  check(preview_style_block(h5p_themer_preview(NULL), 'core') === preview_style_block($core, 'core') && preg_match('~<link\b|\bhref=~i', $without_urls) === 0, 'Embedding cannot turn base_path into a frame asset URL');
}
$GLOBALS['base_path'] = '/';
$GLOBALS['language'] = (object) array('langcode' => 'en" onload="alert(1)');
$lang_doc = preview_document(h5p_themer_preview(NULL));
check(strpos($lang_doc, '<html lang="en&quot; onload=&quot;alert(1)">') !== FALSE, 'Language attribute remains escaped text');
unset($GLOBALS['language']);
$admin_allowed = FALSE;
check(!isset(h5p_themer_preview(NULL)['sample']), 'Direct renderer access denies an unprivileged user');
$admin_allowed = TRUE; $user->uid = 0;
check(!isset(h5p_themer_preview(NULL)['sample']), 'Direct renderer access denies anonymous users');
$user->uid = 1; $h5p_enabled = FALSE;
check(!isset(h5p_themer_preview(NULL)['sample']), 'Unavailable H5P produces diagnostics, no pretend preview');
$h5p_enabled = TRUE; $saved_api = H5PCore::$coreApi; H5PCore::$coreApi['minorVersion'] = 29;
check(!isset(h5p_themer_preview(NULL)['sample']), 'Other Core API versions cannot be silently previewed as 1.28');
H5PCore::$coreApi = $saved_api;
check(preview_services_snapshot() === $before, 'Asset/access/compatibility failures do not write config, caches, public storage or content');
check(h5p_themer_presets($version)['data'] === $catalog_before && h5p_themer_menu() === $routes_before, 'Preview preserves exact catalog and all nine existing routes');

/**
 * Private process-local filesystem double. It never writes or changes a real
 * file: the built-in file wrapper is restored before the test continues.
 */
class H5PThemerPreviewMemoryFile {
  public $context;
  public static $files = array();
  public static $unreadable = NULL;
  private $path;
  private $offset = 0;
  public static function key($path) { return strtolower(str_replace('\\', '/', $path)); }
  public function url_stat($path, $flags) {
    $key = self::key($path);
    return isset(self::$files[$key]) ? array('mode' => 0100444, 'size' => strlen(self::$files[$key]), 'mtime' => 0, 'atime' => 0, 'ctime' => 0, 'uid' => 0, 'gid' => 0) : FALSE;
  }
  public function stream_open($path, $mode, $options, &$opened_path) {
    $this->path = self::key($path);
    if (!isset(self::$files[$this->path]) || $this->path === self::$unreadable) { return FALSE; }
    if ($mode !== 'rb') { throw new LogicException('Preview must only read assets.'); }
    $this->offset = 0;
    return TRUE;
  }
  public function stream_read($count) {
    $value = substr(self::$files[$this->path], $this->offset, $count);
    $this->offset += strlen($value);
    return $value;
  }
  public function stream_eof() { return $this->offset >= strlen(self::$files[$this->path]); }
  public function stream_stat() { return $this->url_stat($this->path, 0); }
  public function stream_write($value) { throw new LogicException('Preview filesystem writes are forbidden.'); }
  public function stream_set_option($option, $arg1, $arg2) { return FALSE; }
}

$core_path = H5PThemerPreviewMemoryFile::key(BACKDROP_ROOT . '/modules/h5p/library/styles/h5p-theme-variables.css');
$sample_path = H5PThemerPreviewMemoryFile::key(dirname(__DIR__) . '/css/h5p_themer.preview.css');
$trusted_assets = array($core_path => $core_source, $sample_path => file_get_contents(dirname(__DIR__) . '/css/h5p_themer.preview.css'));
$before = preview_services_snapshot();
if (!stream_wrapper_unregister('file')) { throw new RuntimeException('Cannot install private filesystem test double.'); }
try {
  if (!stream_wrapper_register('file', 'H5PThemerPreviewMemoryFile')) { throw new RuntimeException('Cannot register private filesystem test double.'); }
  H5PThemerPreviewMemoryFile::$files = $trusted_assets;
  clearstatcache();
  $virtual = h5p_themer_preview(NULL);
  check(preview_style_block($virtual, 'core') === $core_source && preview_style_block($virtual, 'sample') === $trusted_assets[$sample_path], 'Process-local filesystem double exercises both real fixed asset paths');
  foreach (array($core_path, $sample_path) as $asset) {
    foreach (array('missing', 'unreadable', 'empty', 'whitespace', 'invalid_utf8', 'nul', 'closing_comment', 'closing_upper', 'closing_tab', 'closing_line', 'closing_slash', 'closing_eof') as $failure) {
      H5PThemerPreviewMemoryFile::$files = $trusted_assets;
      H5PThemerPreviewMemoryFile::$unreadable = NULL;
      if ($failure === 'missing') { unset(H5PThemerPreviewMemoryFile::$files[$asset]); }
      elseif ($failure === 'unreadable') { H5PThemerPreviewMemoryFile::$unreadable = $asset; }
      else {
        $unsafe = array('empty' => '', 'whitespace' => " \r\n\t", 'invalid_utf8' => "\xFF", 'nul' => "/*\0*/",
          'closing_comment' => '/* </style><script>alert(1)</script> */', 'closing_upper' => '</STYLE >',
          'closing_tab' => "</StYlE\t>", 'closing_line' => "</style\n>", 'closing_slash' => '</style/>', 'closing_eof' => '</style');
        H5PThemerPreviewMemoryFile::$files[$asset] = $unsafe[$failure];
      }
      clearstatcache();
      $result = h5p_themer_preview(array('kind' => 'builtin', 'id' => 'mint', 'version' => $version));
      check(!isset($result['sample']) && !isset($result['core_comparison']) && strpos($result['#markup'], 'assets are unavailable') !== FALSE, 'Asset failure produces a diagnostic, never a partial/fallback frame: ' . basename($asset) . ' ' . $failure);
      check(preview_services_snapshot() === $before, 'Asset failure cannot write config/cache/public storage/content: ' . $failure);
    }
  }
  H5PThemerPreviewMemoryFile::$unreadable = NULL;
  $raw_css = ":root { --example: '\"< & >\"'; }\r\n/* unchanged bytes */\r\n";
  H5PThemerPreviewMemoryFile::$files = array($core_path => $raw_css, $sample_path => $trusted_assets[$sample_path]);
  clearstatcache();
  check(preview_style_block(h5p_themer_preview(NULL), 'core') === $raw_css, 'Raw CSS quotes, angle brackets, ampersands and CRLF are not HTML-escaped or rewritten');
}
finally {
  stream_wrapper_restore('file');
  clearstatcache();
}
check(file_get_contents($h5p_root . '/library/styles/h5p-theme-variables.css') === $core_source && file_get_contents(dirname(__DIR__) . '/css/h5p_themer.preview.css') === $trusted_assets[$sample_path], 'Original filesystem restored; neither real stylesheet was changed');
check(preview_services_snapshot() === $before, 'All embedded-asset checks preserve configuration, default, cache and runtime storage');

// A fresh document and CSS block on every render prevents state carry-over.
$mint = h5p_themer_preview(array('kind' => 'builtin', 'id' => 'mint', 'version' => $version));
$empty = $legacy; $empty['colors'] = array(); seed('h5p_themer.theme.preview_legacy', $empty);
check(preview_css(h5p_themer_preview(NULL)) === '' && preview_css(h5p_themer_preview('preview_legacy', 1)) === '' && strpos(preview_css($mint), '#187571') !== FALSE, 'Mint to Core or empty legacy preview cannot retain stale overrides');
$css = file_get_contents(dirname(__DIR__) . '/css/h5p_themer.preview.css');
preg_match_all('/(--h5p-theme-[a-z0-9-]+)\s*:/', $core_source, $declarations);
preg_match_all('/var\((--h5p-theme-[a-z0-9-]+)/', $css, $consumers);
check(array_diff(array_unique($consumers[1]), array_unique($declarations[1])) === array(), 'Every representative color/spacing/font variable exists in installed Core 1.28');
check(strpos($css, ':root') === FALSE && strpos($css, '@import') === FALSE && strpos($css, 'url(') === FALSE, 'Host stylesheet cannot load external assets or define document-wide Core defaults');
check(strpos($css, '@media (max-width: 40rem)') !== FALSE && strpos($css, 'grid-template-columns: minmax(0, 1fr)') !== FALSE && strpos($css, ':focus-visible') !== FALSE, 'Responsive/focus styles are present; computed behavior remains a Firefox checkpoint');
check(preview_style_block($core, 'density') === '' && preg_match('~class="[^"]*\bh5p-(?:large|medium)\b[^"]*"~', $core_doc) === 0, 'No stored-density class is applied to markup; embedded Core density definitions remain intact');
