const path = require('node:path');
const {loadConfig} = require('./scripts/config.cjs');
const registry = loadConfig();
const prepared = require('./.generated/registry.json');

/** Keep branch/locale symlink identities intact for the docs metadata loader. */
function canonicalPathsPlugin() {
    return {
        name: 'canonical-document-paths',
        /** Avoid resolving linked Markdown outside its configured plugin path. */
        configureWebpack() {
            return {resolve: {symlinks: false}};
        }
    };
}

if (JSON.stringify(registry) !== JSON.stringify(prepared)) {
    throw new Error('Version configuration changed. Run npm run docs:prepare again.');
}

module.exports = {
    title: 'sGallery',
    tagline: 'sGallery documentation for Evolution CMS',
    url: 'https://seiger.github.io',
    baseUrl: '/sGallery/',
    trailingSlash: true,
    favicon: 'img/logo.svg',
    organizationName: 'Seiger',
    projectName: 'sGallery',
    onBrokenLinks: 'throw',
    onBrokenAnchors: 'throw',
    markdown: {format: 'md', hooks: {onBrokenMarkdownLinks: 'throw'}},
    i18n: {
        defaultLocale: registry.defaultLocale,
        locales: Object.keys(registry.locales),
        path: '.generated/i18n',
        localeConfigs: Object.fromEntries(Object.entries(registry.locales)
            .map(([locale, label]) => [locale, {label, htmlLang: registry.localeSources?.[locale] || locale}]))
    },
    customFields: {documentation: registry},
    presets: [['classic', {
        docs: false,
        blog: false,
        pages: false,
        theme: {customCss: require.resolve('./src/css/theme.css')}
    }]],
    plugins: [
        canonicalPathsPlugin,
        ...registry.lines.map((line) => ['@docusaurus/plugin-content-docs', {
            id: line.id,
            path: path.join(__dirname, '.generated/content', line.id),
            routeBasePath: line.route || '/',
            sidebarPath: require.resolve('./sidebars.js'),
            remarkPlugins: [require('./scripts/remark-image-groups.cjs')]
        }]),
        ['@docusaurus/plugin-client-redirects', {
            redirects: [{from: '/search/', to: '/'}]
        }]
    ],
    themeConfig: {
        colorMode: {defaultMode: 'light', respectPrefersColorScheme: true, disableSwitch: false},
        navbar: {
            title: 'sGallery',
            logo: {alt: 'sGallery', src: 'img/logo.svg', width: 28, height: 28},
            items: [
                {type: 'custom-docsLine', position: 'right'},
                {type: 'localeDropdown', position: 'right'},
                {href: 'https://github.com/Seiger/sGallery', label: 'GitHub', position: 'right'}
            ]
        },
        docs: {sidebar: {hideable: true, autoCollapseCategories: true}},
        footer: {
            style: 'light',
            copyright: 'Developed by <a href="https://seigerit.com/" target="_blank" rel="noopener noreferrer">Seiger IT</a>'
        },
        tableOfContents: {minHeadingLevel: 2, maxHeadingLevel: 3},
        prism: {additionalLanguages: ['php', 'bash', 'json']}
    }
};
