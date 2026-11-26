import React from 'react';
import DeveloperCredit from '@site/src/components/DeveloperCredit';

/** Mobile has no persistent sidebar, so keep its credit in a slim fixed bar. */
export default function Footer() {
    return <footer className="docs-mobile-credit"><DeveloperCredit /></footer>;
}
