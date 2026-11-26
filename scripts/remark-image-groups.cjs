/** Merge adjacent image-only Markdown paragraphs without changing canonical files. */
function remarkImageGroups() {
    return (tree) => {
        const isImages = (node) => node?.type === 'paragraph' && node.children.length > 0
            && node.children.some((child) => child.type === 'image')
            && node.children.every((child) => child.type === 'image'
                || (child.type === 'text' && child.value.trim() === ''));
        const grouped = [];
        for (let index = 0; index < tree.children.length; index++) {
            const first = tree.children[index];
            if (!isImages(first)) {
                grouped.push(first);
                continue;
            }
            const children = [...first.children];
            while (isImages(tree.children[index + 1])) {
                children.push({type: 'text', value: '\n'}, ...tree.children[++index].children);
            }
            grouped.push({...first, children});
        }
        tree.children = grouped;
    };
}

module.exports = remarkImageGroups;
