const fs = require('node:fs');
const path = require('node:path');

/**
 * Find missing relative Markdown targets, excluding illustrative fenced examples.
 * @param {string} root Canonical documentation directory to inspect.
 * @returns {Array<object>} Missing source/target pairs.
 */
function checkMarkdown(root) {
    const missing = [];
    for (const entry of fs.readdirSync(root, {recursive: true, withFileTypes: true})) {
        if (!entry.isFile() || !entry.name.endsWith('.md')) continue;
        const file = path.join(entry.parentPath, entry.name);
        const markdown = fs.readFileSync(file, 'utf8').replace(/^```[^\n]*\n[\s\S]*?^```/gm, '');
        for (const match of markdown.matchAll(/\]\(([^\s)]+\.md)(?:#[^)]*)?\)/g)) {
            if (/^(?:https?:|\/)/.test(match[1])) continue;
            if (!fs.existsSync(path.resolve(path.dirname(file), match[1]))) {
                missing.push({file: path.relative(root, file), target: match[1]});
            }
        }
    }
    return missing;
}

if (require.main === module) {
    const missing = checkMarkdown(path.resolve(process.argv[2]));
    process.stdout.write(`${JSON.stringify(missing, null, 2)}\n`);
    process.exitCode = missing.length ? 1 : 0;
}
module.exports = {checkMarkdown};
