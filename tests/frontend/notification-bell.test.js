import test from 'node:test';
import assert from 'node:assert/strict';
import { formatNotificationTime } from '../../resources/js/modules/notification-bell.js';

const referenceTime = Date.parse('2026-09-10T10:00:00Z');

test('formats notification time relatively using the selected locale', () => {
    assert.equal(formatNotificationTime('2026-09-10T09:58:00Z', 'en', referenceTime), '2 minutes ago');
    assert.equal(formatNotificationTime('2026-09-10T09:58:00Z', 'vi-VN', referenceTime), '2 phút trước');
});

test('formats recent notification as now and invalid values as empty', () => {
    assert.equal(formatNotificationTime('2026-09-10T09:59:55Z', 'en', referenceTime), 'now');
    assert.equal(formatNotificationTime('invalid-date', 'en', referenceTime), '');
});
