import test from 'node:test';
import assert from 'node:assert/strict';
import { formatMoney } from '../../resources/js/modules/money.js';

test('formats zero-decimal VND minor units without dividing by one hundred', () => {
    assert.equal(formatMoney(250000, 'VND'), '250,000 VND');
});

test('formats two-decimal USD minor units by converting from minor units', () => {
    assert.equal(formatMoney(1250, 'USD'), '12.50 USD');
});
