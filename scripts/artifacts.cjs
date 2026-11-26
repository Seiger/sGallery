const fs = require('node:fs');
const path = require('node:path');
const {loadConfig} = require('./config.cjs');

// Artifact names come from the same validated registry as the checkout matrix.
for (const {version} of loadConfig().lines) {
    const target = path.resolve(__dirname, '..', '.sources', version);
    fs.mkdirSync(target, {recursive: true});
    fs.renameSync(path.resolve(target, '..', `documentation-${version}`), path.join(target, 'docs'));
}
