import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

test('service worker excludes authenticated and payment routes from navigation cache', async () => {
    const source = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');

    assert.match(source, /isPrivateRoute/);
    assert.match(source, /!isPrivateRoute/);
    assert.match(source, /user\|admin/);
    assert.match(source, /checkout.*payment|payment.*checkout/);
});

test('loads booking interactions on the standalone combo editing page', async () => {
    const source = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8');

    assert.match(source, /data-booking-checkout.*data-combo-total/);
    assert.match(source, /initComboTotals/);
});
