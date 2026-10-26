/** Copy source assets into a disposable, database-free runtime. GPL-3.0-or-later. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const repo = path.resolve(__dirname, '../..');
const source = process.argv[2] && fs.realpathSync(process.argv[2]);
if (!source) throw Error('Usage: node dev/native-density/prepare.cjs <existing-Backdrop-source-root>');
const output = path.join(repo, 'artifacts/native-density-checkpoint1');
if (fs.existsSync(output)) throw Error('Disposable output already exists; preserve it instead of overwriting.');
const core = path.join(source, 'modules/h5p/library');
const libraries = path.join(source, 'files/h5p/libraries');
const corePhp = fs.readFileSync(path.join(core, 'h5p.classes.php'), 'utf8');
if (!/static \$coreApi = array\(\s*'majorVersion' => 1,\s*'minorVersion' => 28\s*\)/.test(corePhp)) throw Error('Requires Core API 1.28.');
function assets(kind) {
  const block = corePhp.match(new RegExp('public static \\$' + kind + ' = array\\(([\\s\\S]*?)\\);'));
  if (!block) throw Error('Core asset list unavailable.');
  return [...block[1].matchAll(/'([^']+)'/g)].map(match => '/assets/core/' + match[1]);
}
const scripts = [], styles = [], visited = new Set(), versions = {};
function visit(name) {
  if (visited.has(name)) return;
  visited.add(name);
  const meta = JSON.parse(fs.readFileSync(path.join(libraries, name, 'library.json'), 'utf8'));
  for (const dep of meta.preloadedDependencies || []) visit(`${dep.machineName}-${dep.majorVersion}.${dep.minorVersion}`);
  versions[name] = `${meta.majorVersion}.${meta.minorVersion}.${meta.patchVersion}`;
  for (const item of meta.preloadedJs || []) scripts.push(`/assets/libraries/${name}/${item.path}`);
  for (const item of meta.preloadedCss || []) styles.push(`/assets/libraries/${name}/${item.path}`);
}
visit('H5P.TrueFalse-1.8');
const coreScripts = assets('scripts'), coreStyles = assets('styles');
// All input reads are confined to public source/library assets, never settings or content.
for (const url of [...coreScripts, ...coreStyles, ...scripts, ...styles]) {
  const input = url.startsWith('/assets/core/') ? path.join(core, url.slice(13)) : path.join(libraries, url.slice(18));
  if (!fs.statSync(input).isFile()) throw Error('Missing asset: ' + input);
}
fs.mkdirSync(output, { recursive: true });
for (const dir of ['js', 'styles', 'fonts', 'images']) fs.cpSync(path.join(core, dir), path.join(output, 'assets/core', dir), { recursive: true });
for (const name of visited) fs.cpSync(path.join(libraries, name), path.join(output, 'assets/libraries', name), { recursive: true });
for (const file of ['controller.js', 'harness.js']) fs.copyFileSync(path.join(__dirname, file), path.join(output, file));
const l10n = {};
const localization = corePhp.match(/public function getLocalization\(\) \{([\s\S]*?)\n  \}/)[1];
for (const match of localization.matchAll(/'(\w+)' => \$this->h5pF->t\('([^']*)'\)/g)) l10n[match[1]] = match[2];
const integration = { baseUrl: '', url: '/content', siteUrl: 'http://127.0.0.1:8767', l10n: { H5P: l10n },
  saveFreq: false, postUserStatistics: false, ajax: {}, fullscreenDisabled: true, theme: { density: 'large' },
  core: { scripts: coreScripts, styles: coreStyles }, contents: {} };
for (const id of [101, 102]) integration.contents['cid-' + id] = {
  library: 'H5P.TrueFalse 1.8', fullScreen: false, metadata: { title: 'Native Density test', defaultLanguage: 'en' },
  jsonContent: JSON.stringify({ question: '<p>The Earth travels around the Sun.</p>', correct: 'true',
    behaviour: { enableRetry: true, enableSolutionsButton: true, enableCheckButton: true, autoCheck: false,
      feedbackOnCorrect: 'Correct. The Earth completes one orbit around the Sun each year.',
      feedbackOnWrong: 'Try again: the Earth does orbit the Sun.' } }),
  displayOptions: { frame: false, export: false, embed: false, copyright: false, icon: false, copy: false },
  contentUserData: { 0: { state: '{}' } }, scripts, styles
};
fs.writeFileSync(path.join(output, 'integration.js'), 'window.H5PIntegration = ' + JSON.stringify(integration, null, 2) + ';\n');
const runtime = '<script src="integration.js"></script>\n' + coreScripts.map(url => `<script src="${url}"></script>`).join('\n');
fs.writeFileSync(path.join(output, 'index.html'), fs.readFileSync(path.join(__dirname, 'index.html'), 'utf8').replace('<!-- RUNTIME -->', runtime));
const hashes = {};
function hashCopies(dir) {
  for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
    const file = path.join(dir, item.name);
    if (item.isDirectory()) hashCopies(file);
    else {
      const relative = path.relative(path.join(output, 'assets'), file).replaceAll('\\', '/');
      const input = relative.startsWith('core/') ? path.join(core, relative.slice(5)) : path.join(libraries, relative.slice(10));
      const digest = buffer => crypto.createHash('sha256').update(buffer).digest('hex');
      hashes[relative] = digest(fs.readFileSync(file));
      if (hashes[relative] !== digest(fs.readFileSync(input))) throw Error('Copy differs from source: ' + relative);
    }
  }
}
hashCopies(path.join(output, 'assets'));
fs.writeFileSync(path.join(output, 'manifest.json'), JSON.stringify({ coreApi: '1.28', source, versions, hashes }, null, 2));
console.log(JSON.stringify({ output, coreApi: '1.28', versions, verifiedAssetFiles: Object.keys(hashes).length }, null, 2));
