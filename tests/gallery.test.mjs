import assert from 'node:assert/strict';
import {test} from 'node:test';
import {createElement as h} from 'react';
import {imageSlides, clampSlide, moveSlide, swipeDirection} from '../src/lib/gallery.mjs';
import remarkImageGroups from '../scripts/remark-image-groups.cjs';

const Image = () => null;

test('a changing Markdown image group supplies its current URLs and count', () => {
    const images = (sources) => sources.flatMap((src) => [h(Image, {src, alt: src}), '\n']);
    assert.deepEqual(imageSlides(images(['a.png', 'b.png']), Image).map((slide) => slide.props.src), ['a.png', 'b.png']);
    assert.deepEqual(imageSlides(images(['new.png', 'b.png', 'c.png']), Image).map((slide) => slide.props.src), ['new.png', 'b.png', 'c.png']);
    assert.equal(imageSlides(images(['a.png']), Image), null);
    assert.equal(imageSlides([], Image), null);
    assert.equal(imageSlides([h('img', {src: 'a.png'}), h('img', {src: 'b.png'})], Image).length, 2);
});

test('ordinary paragraphs, links and invalid image sources keep their Markdown rendering', () => {
    assert.equal(imageSlides(['Explanation ', h(Image, {src: 'a.png'}), h(Image, {src: 'b.png'})], Image), null);
    assert.equal(imageSlides([h('a', {href: 'a.png'}, h(Image, {src: 'a.png'})), h(Image, {src: 'b.png'})], Image), null);
    assert.equal(imageSlides([h(Image, {src: 'a.png'}), h(Image, {src: ''})], Image), null);
});

test('removing the active slide and wraparound navigation never select a missing image', () => {
    assert.equal(clampSlide(4, 2), 1);
    assert.equal(moveSlide(4, 1, 2), 0);
    assert.equal(moveSlide(0, -1, 7), 6);
    assert.equal(moveSlide(6, 1, 7), 0);
    assert.equal(clampSlide(4, 0), 0);
    assert.equal(moveSlide(4, -1, 0), 0);
});

test('swiping changes slides while vertical scrolling and small gestures are ignored', () => {
    assert.equal(swipeDirection(-80, 10), 1);
    assert.equal(swipeDirection(80, 10), -1);
    assert.equal(swipeDirection(40, 0), 0);
    assert.equal(swipeDirection(80, 120), 0);
});

test('blank lines between Markdown images do not split the gallery', () => {
    const paragraph = (...urls) => ({type: 'paragraph', children: urls.map((url) => ({type: 'image', url}))});
    const text = {type: 'paragraph', children: [{type: 'text', value: 'Explanation'}]};
    const tree = {type: 'root', children: [paragraph('a.png'), paragraph('b.png', 'c.png'), text, paragraph('d.png')]};
    remarkImageGroups()(tree);
    assert.equal(tree.children.length, 3);
    assert.deepEqual(tree.children[0].children.filter((node) => node.type === 'image').map((node) => node.url), ['a.png', 'b.png', 'c.png']);
    assert.equal(tree.children[1], text);
    assert.equal(tree.children[2].children[0].url, 'd.png');
});

test('empty documents and headings remain unchanged during image grouping', () => {
    const tree = {type: 'root', children: []};
    remarkImageGroups()(tree);
    assert.deepEqual(tree.children, []);
    const heading = {type: 'heading', depth: 2, children: [{type: 'text', value: 'Screenshots'}]};
    tree.children = [heading];
    remarkImageGroups()(tree);
    assert.equal(tree.children[0], heading);
});
