const fs = require('node:fs');
const {loadConfig} = require('./config.cjs');

const matrix = {include: loadConfig().lines.map(({version, branch}) => ({version, branch}))};
if (process.env.GITHUB_OUTPUT) {
    fs.appendFileSync(process.env.GITHUB_OUTPUT, `matrix=${JSON.stringify(matrix)}\n`);
} else {
    process.stdout.write(`${JSON.stringify(matrix)}\n`);
}
