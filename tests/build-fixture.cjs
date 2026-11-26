const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {validateConfig} = require('../scripts/config.cjs');
const {prepare} = require('../scripts/prepare.cjs');

const root = path.resolve(__dirname, '..');
const fixture = path.join(root, '.test-output');
const config = {defaultLocale: 'en', locales: {en: 'English', uk: 'Українська', ru: 'Русский'}, localeSources: {ru: 'uk'}, lines: [
    {version: '3.x', branch: '3.x', label: '3.x', status: 'current'},
    {version: '2.x', branch: '2.x', label: '2.x', status: 'previous'},
    {version: '1.x', branch: '1.x', label: '1.x', status: 'legacy'}
]};
const sources = {};

// These synthetic pages prove routing and asset isolation; they are not release docs.
for (const {version} of config.lines) {
    sources[version] = path.join(fixture, version);
    const docs = path.join(sources[version], 'docs');
    fs.mkdirSync(docs, {recursive: true});
    fs.writeFileSync(path.join(docs, 'docs.json'), JSON.stringify({package: 'seiger/sgallery'}));
    for (const locale of ['en', 'uk']) {
        const localized = path.join(docs, locale);
        fs.mkdirSync(path.join(localized, 'img'), {recursive: true});
        fs.writeFileSync(path.join(localized, 'README.md'),
            `---\nid: intro\nslug: /\n---\n\n# Fixture ${version} ${locale}\n\n[Products](products.md)\n\n![Sample](img/sample.svg)\n`);
        fs.writeFileSync(path.join(localized, 'products.md'),
            `# Products ${version} ${locale}\n\n[Home](README.md)\n`);
        fs.writeFileSync(path.join(localized, 'img/sample.svg'),
            '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="blue"/></svg>');
        if (version === '3.x') fs.writeFileSync(path.join(localized, 'new-page.md'), '# Only in 3.x\n');
    }
}
const configPath = path.join(fixture, 'config.json');
fs.writeFileSync(configPath, JSON.stringify(config));
prepare(validateConfig(config), sources, root);
const result = spawnSync(process.execPath, [path.join(root, 'node_modules/@docusaurus/core/bin/docusaurus.mjs'),
    'build', '--out-dir', 'build-fixture'], {cwd: root, stdio: 'inherit',
    env: {...process.env, DOCS_CONFIG: configPath}});
if (result.status !== 0) process.exit(result.status || 1);

for (const {version, status} of config.lines) {
    for (const locale of ['en', 'uk', 'ru']) {
        const prefix = `${locale === 'en' ? '' : `${locale}/`}${status === 'current' ? '' : `${version}/`}`;
        const html = fs.readFileSync(path.join(root, 'build-fixture', prefix, 'index.html'), 'utf8');
        assert.ok(html.includes(`Fixture ${version} ${locale === 'ru' ? 'uk' : locale}`));
        if (locale === 'ru') assert.match(html, /<html[^>]*lang="uk"/);
        assert.ok(!html.includes('pagination-nav'));
        assert.ok(!html.includes('theme-edit-this-page'));
        assert.ok(html.includes('docs-sidebar-credit'));
        assert.ok(html.includes('https://seigerit.com/'));
        assert.equal(html.includes('class="docs-legacy"'), status !== 'current');
        assert.ok(html.includes(`/sGallery/${prefix}products/`));
        const assetPrefix = `/sGallery/${locale === 'en' ? '' : `${locale}/`}assets/`;
        assert.ok(html.includes(assetPrefix));
        assert.match(html, /alt="Sample"/);
        assert.ok(fs.existsSync(path.join(root, 'build-fixture', prefix, 'products/index.html')));
    }
}
process.stdout.write('Three-line multilingual build, minimal shell, legacy banners, links and assets verified.\n');
