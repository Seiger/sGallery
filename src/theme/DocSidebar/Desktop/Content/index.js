import React from 'react';
import Content from '@theme-original/DocSidebar/Desktop/Content';
import DeveloperCredit from '@site/src/components/DeveloperCredit';

export default function SidebarContent(props) {
    return <>
        <Content {...props} />
        <DeveloperCredit className="docs-sidebar-credit" />
    </>;
}
