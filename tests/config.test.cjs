const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {loadConfig, validateConfig} = require('../scripts/config.cjs');
const {prepare} = require('../scripts/prepare.cjs');

test('production registry has one explicit current major branch', () => {
    const config = loadConfig(path.resolve(__dirname, '../docs.config.json'));
    assert.equal(config.lines.filter((line) => line.status === 'current').length, 1);
    assert.equal(config.lines.find((line) => line.status === 'current').route, '');
});

test('supports three lines and rejects ambiguous or unsafe registries', () => {
    const config = {defaultLocale: 'en', locales: {en: 'English', uk: 'Українська'}, lines: [
        {version: '3.x', branch: '3.x', label: '3.x', status: 'current'},
        {version: '2.x', branch: '2.x', label: '2.x', status: 'previous'},
        {version: '1.x', branch: '1.x', label: '1.x', status: 'legacy'}
    ]};
    assert.deepEqual(validateConfig(config).lines.map((line) => line.route), ['', '2.x', '1.x']);
    assert.throws(() => validateConfig({...config, lines: [config.lines[0], config.lines[0]]}));
    assert.throws(() => validateConfig({...config, lines: [config.lines[2]]}));
    assert.throws(() => validateConfig({...config, lines: [{...config.lines[0], branch: 'master'}]}));
    assert.throws(() => validateConfig({...config, lines: [{...config.lines[0], version: '../outside'}]}));
    assert.throws(() => validateConfig({...config, localeSources: {uk: '../outside'}}));
    assert.throws(() => validateConfig({...config, localeSources: {uk: 'en', en: 'uk'}}));
});

test('build views link canonical sources without modifying them on repeated preparation', (t) => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sgallery-docs-'));
    t.after(() => fs.rmSync(root, {recursive: true, force: true}));
    const config = validateConfig({defaultLocale: 'en', locales: {en: 'English', uk: 'Українська', ru: 'Русский'},
        localeSources: {ru: 'uk'},
        lines: [{version: '1.x', branch: '1.x', label: '1.x', status: 'current'}]});
    const docs = path.join(root, 'package', 'docs');
    for (const locale of ['en', 'uk']) {
        fs.mkdirSync(path.join(docs, locale), {recursive: true});
        fs.writeFileSync(path.join(docs, locale, 'README.md'), `# ${locale}`);
    }
    fs.writeFileSync(path.join(docs, 'docs.json'), JSON.stringify({package: 'seiger/sgallery'}));
    fs.mkdirSync(path.join(root, 'i18n/uk'), {recursive: true});
    fs.writeFileSync(path.join(root, 'i18n/uk/code.json'), JSON.stringify({'docs.status.current': {message: 'Актуальна'}}));
    prepare(config, {'1.x': 'package'}, root);
    prepare(config, {'1.x': 'package'}, root);
    assert.equal(fs.readFileSync(path.join(docs, 'en/README.md'), 'utf8'), '# en');
    const view = path.join(root, '.generated/content/line-1-x');
    assert.ok(fs.lstatSync(view).isSymbolicLink());
    assert.equal(fs.realpathSync(view), fs.realpathSync(path.join(docs, 'en')));
    assert.equal(fs.readFileSync(path.join(root,
        '.generated/i18n/uk/docusaurus-plugin-content-docs-line-1-x/current/README.md'), 'utf8'), '# uk');
    assert.ok(!fs.existsSync(path.join(root, 'versioned_docs')));
    const alias = path.join(root, '.generated/i18n/ru/docusaurus-plugin-content-docs-line-1-x/current');
    assert.equal(fs.realpathSync(alias), fs.realpathSync(path.join(docs, 'uk')));
    const aliasUi = JSON.parse(fs.readFileSync(path.join(root, '.generated/i18n/ru/code.json'), 'utf8'));
    assert.equal(aliasUi['docs.status.current'].message, 'Актуальна');
    assert.equal(aliasUi['theme.CodeBlock.copy'].message, 'Копіювати');
});
