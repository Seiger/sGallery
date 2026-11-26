import React, {cloneElement, useEffect, useRef, useState} from 'react';
import {translate} from '@docusaurus/Translate';
import {clampSlide, moveSlide, swipeDirection} from '@site/src/lib/gallery.mjs';
import styles from './styles.module.css';

/** Slides come entirely from the current Markdown paragraph, including after HMR. */
export default function ScreenshotGallery({slides = []}) {
    const [index, setIndex] = useState(0);
    const [failed, setFailed] = useState({});
    const gesture = useRef(null);
    const count = slides.length;
    const current = clampSlide(index, count);
    useEffect(() => setIndex((value) => clampSlide(value, count)), [count]);

    if (!count) return null;
    const title = (slide, number) => slide.props.alt || translate({
        id: 'gallery.screenshot', message: 'Screenshot {number}',
    }, {number: number + 1});
    const move = (direction) => setIndex((value) => moveSlide(value, direction, count));
    const onKeyDown = (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey) return;
        switch (event.key) {
            case 'ArrowLeft': move(-1); break;
            case 'ArrowRight': move(1); break;
            case 'Home': setIndex(0); break;
            case 'End': setIndex(count - 1); break;
            default: return;
        }
        event.preventDefault();
    };

    return <section className={styles.gallery} role="region"
        aria-roledescription={translate({id: 'gallery.carousel', message: 'carousel'})}
        aria-label={translate({id: 'gallery.label', message: 'sGallery screenshots'})}
        onKeyDown={onKeyDown}>
        <div className={styles.stage}
            onTouchStart={(event) => {
                gesture.current = event.touches.length === 1
                    ? {x: event.touches[0].clientX, y: event.touches[0].clientY} : null;
            }}
            onTouchCancel={() => { gesture.current = null; }}
            onTouchEnd={(event) => {
                const start = gesture.current;
                gesture.current = null;
                if (!start || !event.changedTouches.length) return;
                const touch = event.changedTouches[0];
                const direction = swipeDirection(touch.clientX - start.x, touch.clientY - start.y);
                if (direction) move(direction);
            }}>
            {slides.map((slide, number) => <div key={`${slide.props.src}:${number}`}
                className={styles.slide} hidden={number !== current} role="group"
                aria-roledescription={translate({id: 'gallery.slide', message: 'slide'})}
                aria-label={`${number + 1} / ${count}`}>
                <a href={slide.props.src} target="_blank" rel="noopener noreferrer"
                    className={styles.imageLink}
                    aria-label={translate({id: 'gallery.open', message: 'Open {title} at full size'},
                        {title: title(slide, number)})}>
                    {failed[slide.props.src]
                        ? <span className={styles.error}>{translate({id: 'gallery.unavailable',
                            message: 'Image unavailable. Open the original image.'})}</span>
                        : cloneElement(slide, {
                            className: [slide.props.className, styles.image].filter(Boolean).join(' '),
                            loading: number === 0 ? 'eager' : 'lazy',
                            draggable: false,
                            onError: () => setFailed((value) => ({...value, [slide.props.src]: true})),
                        })}
                </a>
            </div>)}
        </div>
        <div className={styles.controls}>
            <button type="button" className={styles.arrow} onClick={() => move(-1)}
                aria-label={translate({id: 'gallery.previous', message: 'Previous screenshot'})}>
                <span aria-hidden="true">←</span>
            </button>
            <div className={styles.dots}>
                {slides.map((slide, number) => <button type="button"
                    key={`${slide.props.src}:${number}`} className={styles.dot}
                    aria-label={translate({id: 'gallery.show', message: 'Show screenshot {number}: {title}'},
                        {number: number + 1, title: title(slide, number)})}
                    aria-current={current === number ? 'true' : undefined}
                    onClick={() => setIndex(number)}><span /></button>)}
            </div>
            <button type="button" className={styles.arrow} onClick={() => move(1)}
                aria-label={translate({id: 'gallery.next', message: 'Next screenshot'})}>
                <span aria-hidden="true">→</span>
            </button>
        </div>
        <p className={styles.caption} aria-live="polite" aria-atomic="true">
            {current + 1} / {count} — {title(slides[current], current)}
        </p>
        <p className={styles.hint}>{translate({id: 'gallery.hint',
            message: 'Use the arrows or swipe to browse. Select an image to open it at full size.'})}</p>
    </section>;
}
