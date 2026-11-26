/**
 * Determine the line from a localized URL, matching complete path segments.
 * @param {string} pathname Current pathname.
 * @param {object} registry Documentation configuration.
 * @param {string} baseUrl Unlocalized GitHub Pages base URL.
 * @returns {object} Selected line, including its explicit status.
 */
export function selectedLine(pathname, registry, baseUrl = '/sGallery/') {
    const parts = pathname.slice(baseUrl.length).split('/').filter(Boolean);
    if (registry.locales[parts[0]]) parts.shift();
    return registry.lines.find((line) => line.route && line.route === parts[0])
        || registry.lines.find((line) => line.status === 'current');
}

/**
 * Preserve the matching document only when the target line actually contains it.
 * Docusaurus provides localized permalinks; otherwise use the target locale root.
 * @param {object} target Destination line.
 * @param {string} pathname Current pathname.
 * @param {object} allDocs Docusaurus global docs data.
 * @param {string} locale Current locale.
 * @param {object} registry Documentation configuration.
 * @returns {string} A known target URL without a potentially stale hash.
 */
export function lineHref(target, pathname, allDocs, locale, registry) {
    const normalize = (url) => url.replace(/\/$/, '');
    const documents = Object.values(allDocs).flatMap((plugin) =>
        plugin.versions.flatMap((version) => version.docs));
    const current = documents.find((doc) => normalize(doc.path || doc.permalink) === normalize(pathname));
    const equivalent = current && allDocs[target.id]?.versions.flatMap((version) => version.docs)
        .find((doc) => doc.id === current.id);
    if (equivalent) return equivalent.path || equivalent.permalink;
    const localePath = locale === registry.defaultLocale ? '' : `${locale}/`;
    return `/sGallery/${localePath}${target.route ? `${target.route}/` : ''}`;
}
