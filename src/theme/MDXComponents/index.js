import React from 'react';
import MDXComponents from '@theme-original/MDXComponents';
import ScreenshotGallery from '@site/src/components/ScreenshotGallery';
import {imageSlides} from '@site/src/lib/gallery.mjs';

/** Keep portable Markdown as the source of truth for both dDocs and this site. */
function Paragraph({children, ...props}) {
    const slides = imageSlides(children, MDXComponents.img);
    return slides ? <ScreenshotGallery slides={slides} /> : <p {...props}>{children}</p>;
}

export default {...MDXComponents, p: Paragraph};
