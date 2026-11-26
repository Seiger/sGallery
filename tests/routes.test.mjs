import assert from 'node:assert/strict';
import {test} from 'node:test';
import {selectedLine, lineHref} from '../src/lib/routes.mjs';

const registry = {defaultLocale: 'en', locales: {en: 'English', uk: 'Українська'}, lines: [
    {id: 'line-3-x', label: '3.x', route: '', status: 'current'},
    {id: 'line-2-x', label: '2.x', route: '2.x', status: 'previous'},
    {id: 'line-1-x', label: '1.x', route: '1.x', status: 'legacy'}
]};

test('recognizes localized current, previous and legacy routes without prefix collisions', () => {
    assert.equal(selectedLine('/sGallery/', registry).label, '3.x');
    assert.equal(selectedLine('/sGallery/uk/2.x/products/', registry).label, '2.x');
    assert.equal(selectedLine('/sGallery/1.x/products/', registry).label, '1.x');
    assert.equal(selectedLine('/sGallery/1.xyz/', registry).label, '3.x');
});

test('version switching preserves known document IDs and falls back for removed pages', () => {
    const data = {
        'line-3-x': {versions: [{docs: [{id: 'products', path: '/sGallery/uk/products/'}]}]},
        'line-1-x': {versions: [{docs: [{id: 'products', path: '/sGallery/uk/1.x/catalog/'}]}]}
    };
    assert.equal(lineHref(registry.lines[2], '/sGallery/uk/products/', data, 'uk', registry),
        '/sGallery/uk/1.x/catalog/');
    assert.equal(lineHref(registry.lines[1], '/sGallery/uk/products/', data, 'uk', registry),
        '/sGallery/uk/2.x/');
    assert.equal(lineHref(registry.lines[0], '/sGallery/1.x/removed/', data, 'en', registry),
        '/sGallery/');
});
