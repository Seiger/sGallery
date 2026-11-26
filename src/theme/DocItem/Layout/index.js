import React from 'react';
import Layout from '@theme-original/DocItem/Layout';
import Link from '@docusaurus/Link';
import Translate from '@docusaurus/Translate';
import {useLocation} from '@docusaurus/router';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import {useAllDocsData} from '@docusaurus/plugin-content-docs/client';
import {selectedLine, lineHref} from '../../../lib/routes.mjs';

/** Keep the branch warning in the site shell, never in canonical package Markdown. */
export default function DocLayout(props) {
    const {siteConfig, i18n} = useDocusaurusContext();
    const registry = siteConfig.customFields.documentation;
    const {pathname} = useLocation();
    const allDocs = useAllDocsData();
    const selected = selectedLine(pathname, registry);
    const current = registry.lines.find((line) => line.status === 'current');
    return <>
        {selected.status !== 'current' && <aside className="docs-legacy" aria-label="Documentation version">
            <strong>sGallery {selected.label}</strong>{' — '}
            <Translate id="docs.legacy.message">You are reading an older documentation line.</Translate>{' '}
            <Link to={lineHref(current, pathname, allDocs, i18n.currentLocale, registry)}>
                <Translate id="docs.legacy.current">Read current documentation</Translate> ({current.label})
            </Link>
        </aside>}
        <Layout {...props} />
    </>;
}
