import {readFile} from 'node:fs/promises';

/** Exercise real manager sessions with a caller-provided Playwright browser and disposable site. */
export async function runManagerRegressions(browser, {baseUrl, username, password, documentId = 1}) {
    const context = await browser.newContext();
    const errors = [];
    const coreUnloadNotices = [];
    const results = [];
    const png = await readFile(new URL('../fixtures/heif-rgba-source.png', import.meta.url));
    const pdf = Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF');
    const check = (ok, label) => { if (!ok) throw new Error(label); };
    const watch = (page, shell) => page.on('pageerror', error => {
        const notice = {message: error.message, stack: error.stack};
        // CMS 3.5.9 also assumes a parent menu when unloading a standalone form.
        // Keep this verified core notice separate; package and other errors still fail.
        if (!shell && error.message === "Cannot read properties of undefined (reading 'work')" &&
            error.stack.includes('document_onunload') && error.stack.includes('/manager/media/script/main.js:426:')) {
            coreUnloadNotices.push(notice);
        } else errors.push(notice);
    });
    try {
        const manager = await context.newPage();
        watch(manager, true);
        await manager.goto(baseUrl + '/manager/');
        await manager.locator('input[name=username]').fill(username);
        await manager.locator('input[type=password]').fill(password);
        await manager.getByRole('button', {name: /Увійти|Login|Войти/}).click();
        await manager.frameLocator('#mainframe').locator('body').waitFor();
        const managerFrame = await (await manager.locator('#mainframe').elementHandle()).contentFrame();
        for (const shell of [true, false]) {
            const page = shell ? manager : await context.newPage();
            if (!shell) watch(page, false);
            const frame = shell ? managerFrame : page.mainFrame();
            await frame.goto(baseUrl + '/manager/index.php?a=27&id=' + documentId);
            await frame.getByRole('heading', {name: /Галерея|Gallery/i}).click();
            const initial = await frame.locator('#uploadBase1 > li').count();
            const hidden = () => frame.waitForFunction(() => {
                const loader = window.parent.document.getElementById('mainloader');
                return !loader || !loader.classList.contains('show');
            }, undefined, {timeout: 8000});
            await frame.locator('#filesToUpload1').setInputFiles([]);
            await hidden();

            const upload = async (selector, payload, endpoint, expected) => {
                const pending = page.waitForResponse(response => response.url().includes('/sgallery/' + endpoint), {timeout: 10000});
                await frame.locator(selector).setInputFiles(payload);
                const response = await pending;
                const data = await response.json();
                check(response.status() === expected, endpoint + ' HTTP ' + response.status());
                if (expected === 200) check(data.success === 1, endpoint + ' did not succeed: ' + JSON.stringify(data));
                await hidden();
                return data;
            };
            const image = await upload('#filesToUpload1', {name: 'manager-regression.png', mimeType: 'image/png', buffer: png}, 'upload-file', 200);
            const imageId = image.preview.match(/data-sgallery="(\d+)"/)[1];

            // Use the input contract of sectionDownloads with the same delegated listener.
            // Resource tabs normally expose only media uploads; custom integrations use this input.
            await frame.evaluate(() => {
                const input = document.createElement('input');
                input.type = 'file'; input.id = 'filesToUploadDownload1'; input.hidden = true;
                document.getElementById('uploadBase1').before(input);
            });
            const download = await upload('#filesToUploadDownload1', {name: 'manager-regression.pdf', mimeType: 'application/pdf', buffer: pdf}, 'upload-download', 200);
            const downloadId = download.preview.match(/data-sgallery="(\d+)"/)[1];
            await upload('#filesToUpload1', {name: 'invalid.png', mimeType: 'image/png', buffer: Buffer.from('not an image')}, 'upload-file', 422);
            await frame.getByRole('button', {name: 'OK', exact: true}).click();

            const card = frame.locator('#uploadBase1 > li[data-sgallery="' + imageId + '"]');
            const filename = await card.locator('img').getAttribute('alt');
            await card.hover();
            await card.locator('[data-image-remove]').click();
            const deleted = page.waitForResponse(response => response.url().includes('/sgallery/delete'), {timeout: 8000});
            await frame.getByRole('button', {name: /^(Видалити|Delete|Удалить)$/}).click();
            const response = await deleted;
            const message = await response.json();
            check(response.status() === 200 && message.success === 1 && ['Deleted', 'Видалено'].includes(message.message), 'Delete did not return a localized success message');
            const removed = await context.request.get(baseUrl + '/assets/sgallery/resource/' + documentId + '/' + filename);
            check(removed.status() === 404, 'Deleted upload still exists over HTTP');
            const detached = await frame.evaluate(async id => {
                const response = await sGalleryFetch1('/manager/sgallery/delete', {method: 'POST', body: new URLSearchParams({item: id})});
                return {status: response.status, data: await response.json()};
            }, downloadId);
            check(detached.status === 200 && detached.data.success === 1, 'Download cleanup failed');
            // The download was deleted through HTTP; reload to assert persisted gallery state.
            await frame.goto(frame.url());
            await frame.getByRole('heading', {name: /Галерея|Gallery/i}).click();
            check(await frame.locator('#uploadBase1 > li').count() === initial, 'The regression changed pre-existing gallery entries');
            results.push({shell, imageUpload: 200, downloadUpload: 200, invalidImage: 422, emptySelection: true, loaderHidden: true, deleteMessage: message.message, originalRemoved: true, galleryPreserved: true});
            if (shell) await frame.getByRole('link', {name: /^(Скасувати|Cancel|Отмена)$/}).click();
            if (!shell) await page.close();
        }
        check(errors.length === 0, 'Browser errors: ' + JSON.stringify(errors));
        return {baseUrl, results, pageErrors: errors, coreUnloadNotices};
    } finally {
        await context.close();
    }
}
