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
