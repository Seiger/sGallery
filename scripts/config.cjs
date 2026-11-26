const fs = require('node:fs');
const path = require('node:path');

/**
 * Validate the single version registry before it drives routes or CI checkouts.
 * @param {object} config Parsed registry.
 * @returns {object} Validated registry with stable plugin IDs and routes.
 */
function validateConfig(config) {
    if (!config.locales?.[config.defaultLocale] || !config.lines?.length) {
        throw new Error('Declare a default locale and at least one documentation line.');
    }
    if (Object.keys(config.locales).some((locale) => !/^[a-z]{2}$/.test(locale))) {
        throw new Error('Locale keys must be two lowercase letters.');
    }
    for (const [locale, source] of Object.entries(config.localeSources || {})) {
        if (!config.locales[locale] || !config.locales[source] || config.localeSources[source]) {
            throw new Error('Locale sources must reference a declared, non-aliased locale.');
        }
    }
    if (config.lines.filter((line) => line.status === 'current').length !== 1) {
        throw new Error('Exactly one documentation line must be current.');
    }
    const versions = new Set();
    const lines = config.lines.map((line) => {
        if (!/^[1-9]\d*\.x$/.test(line.version) || versions.has(line.version)
            || line.branch !== line.version || !line.label
            || !['current', 'previous', 'legacy'].includes(line.status)) {
            throw new Error(`Invalid or duplicate documentation line: ${line.version}`);
        }
        versions.add(line.version);
        return {...line, id: `line-${line.version.replace('.', '-')}`,
            route: line.status === 'current' ? '' : line.version};
    });
    return {...config, lines};
}

/**
 * Load production configuration, or an explicitly supplied local test registry.
 * @param {string} [filename] Optional registry path.
 * @returns {object} Validated registry.
 */
function loadConfig(filename = process.env.DOCS_CONFIG || path.resolve(__dirname, '../docs.config.json')) {
    return validateConfig(JSON.parse(fs.readFileSync(filename, 'utf8')));
}

module.exports = {loadConfig, validateConfig};
