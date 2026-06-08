import { expect, test } from '@playwright/test';

/**
 * Smoke tests — verify the SPA boots, the login page renders, and the
 * routing layer responds to URL changes. Fast (<5s) and would catch any
 * "white screen on load" regression from a future PR.
 */

test.describe('SPA boot', () => {
    test('renders the login page when unauthenticated', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('heading', { name: 'Philippine Accounting System' })).toBeVisible();
        await expect(page.getByPlaceholder(/email/i).or(page.getByLabel(/email/i))).toBeVisible();
    });

    test('email validation blocks submit when malformed', async ({ page }) => {
        await page.goto('/');
        await page.getByLabel(/email/i).fill('not-an-email');
        await page.getByLabel(/password/i).fill('whatever');
        await page.getByRole('button', { name: /sign in/i }).click();

        // Zod-resolver error surfaces inline; either the button stays disabled
        // or an error message appears. We just want SOME signal of validation.
        await expect(page.getByText(/invalid email/i)).toBeVisible();
    });

    test('shows a meaningful error on bad credentials', async ({ page }) => {
        await page.goto('/');
        await page.getByLabel(/email/i).fill('nobody@example.com');
        await page.getByLabel(/password/i).fill('definitely-wrong-password');
        await page.getByRole('button', { name: /sign in/i }).click();

        // Backend returns 401; SPA surfaces the "sign-in failed" banner.
        await expect(page.getByText(/sign-in failed/i)).toBeVisible({ timeout: 10_000 });
    });
});

test.describe('hash routing while unauthenticated', () => {
    test('direct-URL access to a protected page still lands on login', async ({ page }) => {
        await page.goto('/#/sales/invoices');
        // Auth gate redirects to login (not the SI list).
        await expect(page.getByRole('heading', { name: 'Philippine Accounting System' })).toBeVisible();
    });

    test('hash changes do not trigger a full page reload', async ({ page }) => {
        await page.goto('/');
        const initial = await page.evaluate(() => performance.now());
        await page.evaluate(() => { window.location.hash = '#/something'; });
        const after = await page.evaluate(() => performance.now());
        // If a reload had happened, performance.now() would reset to ~0.
        expect(after).toBeGreaterThanOrEqual(initial);
    });
});
