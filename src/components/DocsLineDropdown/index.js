import React from 'react';
import {useLocation} from '@docusaurus/router';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import {useAllDocsData} from '@docusaurus/plugin-content-docs/client';
import {translate} from '@docusaurus/Translate';
import DropdownNavbarItem from '@theme/NavbarItem/DropdownNavbarItem';
import {selectedLine, lineHref} from '../../lib/routes.mjs';

/** Render branch-backed choices using known document routes, including mobile navigation. */
export default function DocsLineDropdown(props) {
    const {siteConfig, i18n} = useDocusaurusContext();
    const registry = siteConfig.customFields.documentation;
    const {pathname} = useLocation();
    const allDocs = useAllDocsData();
    const selected = selectedLine(pathname, registry);
    const labels = {
        current: translate({id: 'docs.status.current', message: 'Current'}),
        previous: translate({id: 'docs.status.previous', message: 'Previous'}),
        legacy: translate({id: 'docs.status.legacy', message: 'Legacy'})
    };
    return <DropdownNavbarItem {...props}
        label={`${selected.label} · ${labels[selected.status]}`}
        items={registry.lines.map((line) => ({
            label: `${line.label} · ${labels[line.status]}`,
            to: lineHref(line, pathname, allDocs, i18n.currentLocale, registry),
            isActive: () => line.id === selected.id
        }))} />;
}
