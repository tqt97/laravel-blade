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

    assert.match(source, /hasStandaloneComboTotals/);
    assert.match(source, /data-seat-picker/);
    assert.match(source, /if \(hasStandaloneComboTotals\) initComboTotals/);
});

test('uses explicit payment submit hooks instead of the coupon submit button', async () => {
    const source = await readFile(new URL('../../resources/js/modules/booking.js', import.meta.url), 'utf8');

    assert.match(source, /data-payment-submit/);
    assert.match(source, /event\.submitter/);
});

test('refreshes combo availability from both checkout and standalone combo forms', async () => {
    const source = await readFile(new URL('../../resources/js/modules/booking.js', import.meta.url), 'utf8');

    assert.match(source, /availabilityRoot = checkout \?\? form/);
    assert.match(source, /availabilityRoot\.dataset\.comboAvailabilityUrl/);
});

test('stops checkout interactions and combo polling after the hold expires', async () => {
    const source = await readFile(new URL('../../resources/js/modules/booking.js', import.meta.url), 'utf8');

    assert.match(source, /booking:expired/);
    assert.match(source, /bookingExpired/);
    assert.match(source, /booking:expired/);
    assert.match(source, /control\.disabled = true/);
    assert.match(source, /reselectUrl/);
});

test('focuses an accessible seat conflict message and highlights lost seats', async () => {
    const source = await readFile(new URL('../../resources/js/modules/seat-picker.js', import.meta.url), 'utf8');

    assert.match(source, /data-seat-conflict-region/);
    assert.match(source, /lostAvailability/);
    assert.match(source, /notice\.focus/);
});
