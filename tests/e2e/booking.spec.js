import { expect, test } from '@playwright/test';

const movieSlug = process.env.E2E_MOVIE_SLUG;
const screeningId = process.env.E2E_SCREENING_ID;
const email = process.env.E2E_EMAIL;
const password = process.env.E2E_PASSWORD;
const stripeThreeDsCard = process.env.E2E_STRIPE_3DS_CARD ?? '4000002500003155';

const hasBookingFixture = Boolean(movieSlug && screeningId && email && password);

const signInIfRequired = async (page) => {
    if (!page.url().includes('/login')) return;

    await page.getByLabel(/email/i).fill(email);
    await page.getByLabel(/password/i).fill(password);
    await page.getByRole('button', { name: /sign in|đăng nhập/i }).click();
};

const holdOneSeat = async (page) => {
    await page.goto(`/movies/${movieSlug}/showtimes/${screeningId}`);
    const seat = page.locator('[data-seat-id]:not([disabled])').first();
    await expect(seat).toBeVisible();
    await seat.click();
    await page.locator('[data-seat-submit]').click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: /confirm|xác nhận/i }).click();
    await signInIfRequired(page);
    await expect(page).toHaveURL(/\/user\/bookings\/\d+\/checkout/);
};

test.describe('booking flow', () => {
    test.beforeEach(() => {
        test.skip(!hasBookingFixture, 'Set E2E_MOVIE_SLUG, E2E_SCREENING_ID, E2E_EMAIL and E2E_PASSWORD for a seeded test environment.');
    });

    test('selects a seat, confirms the dialog and reaches checkout', async ({ page }) => {
        await holdOneSeat(page);
        await expect(page.locator('[data-booking-checkout]')).toBeVisible();
        await expect(page.locator('[data-payment-submit]')).toBeEnabled();
    });

    test('keeps combo availability and quantity controls usable on checkout', async ({ page }) => {
        await holdOneSeat(page);
        const bookingId = page.url().match(/\/user\/bookings\/(\d+)\/checkout/)?.[1];
        await page.goto(`/user/bookings/${bookingId}/combos`);
        await expect(page.locator('[data-combo-availability-url]')).toBeVisible();
        await expect(page.locator('[data-combo-total]')).toBeVisible();
    });
});

test.describe('Stripe 3DS flow', () => {
    test.beforeEach(() => {
        test.skip(!hasBookingFixture, 'Set E2E booking fixture variables before running Stripe 3DS E2E.');
    });

    test('opens the Payment Element and completes the 3DS test-card flow', async ({ page }) => {
        await holdOneSeat(page);
        await page.locator('[data-payment-submit]').first().click();
        await expect(page).toHaveURL(/\/user\/bookings\/\d+\/payment-action/);

        const paymentStatus = page.locator('[data-payment-status]');
        await expect(paymentStatus).toBeVisible();
        const paymentFrame = page.frameLocator('iframe[title*="Secure payment"]');
        await paymentFrame.getByRole('textbox', { name: /card number/i }).fill(stripeThreeDsCard);
        await paymentFrame.getByRole('textbox', { name: /expiration date/i }).fill('12/34');
        await paymentFrame.getByRole('textbox', { name: /security code/i }).fill('123');

        await page.getByRole('button', { name: /pay|thanh toán/i }).click();
        await expect(page).toHaveURL(/\/user\/bookings\/(?:\d+\/success|\d+\/payment-action)/, { timeout: 90_000 });
    });
});
