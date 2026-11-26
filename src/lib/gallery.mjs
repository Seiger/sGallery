import {Children, isValidElement} from 'react';

/** Only enhance image-only paragraphs; normal prose and linked images stay intact. */
export function imageSlides(children, imageComponent) {
    const nodes = Children.toArray(children).filter((node) =>
        typeof node !== 'string' || node.trim() !== '');
    if (nodes.length < 2 || !nodes.every((node) => isValidElement(node)
        && (node.type === 'img' || node.type === imageComponent)
        && typeof node.props.src === 'string' && node.props.src.trim() !== '')) return null;
    return nodes;
}

export function clampSlide(index, count) {
    return count > 0 ? Math.max(0, Math.min(index, count - 1)) : 0;
}

export function moveSlide(index, direction, count) {
    return count > 0 ? (clampSlide(index, count) + direction + count) % count : 0;
}

/** Leave vertical page scrolling and small accidental gestures alone. */
export function swipeDirection(deltaX, deltaY) {
    return Math.abs(deltaX) >= 50 && Math.abs(deltaX) > Math.abs(deltaY)
        ? (deltaX < 0 ? 1 : -1) : 0;
}
