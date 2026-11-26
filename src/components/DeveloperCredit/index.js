import React from 'react';
import {useThemeConfig} from '@docusaurus/theme-common';
import FooterCopyright from '@theme/Footer/Copyright';

export default function DeveloperCredit({className = ''}) {
    const {footer} = useThemeConfig();
    return <div className={`docs-developer-credit ${className}`}>
        <FooterCopyright copyright={footer.copyright} />
    </div>;
}
