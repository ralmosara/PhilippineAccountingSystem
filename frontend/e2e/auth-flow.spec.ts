import { expect, test } from '@playwright/test';

/**
 * Authenticated journey smoke — exercises the bootstrap → login → dashboard
 * → page navigation → logout cycle once the backend is up.
 *
 * Skips if the seeded admin credentials aren't available; CI populates them
 * via `php artisan db:seed` before running the e2e job.
 */

const SEEDED_EMAIL = process.env.E2E_ADMIN_EMAIL ?? 'admin@test.com';
const SEEDED_PASS  = process.env.E2E_ADMIN_PASSWORD ?? 'secret123';

test.describe('authenticated journey', () => {
    test.beforeEach(async ({ page }) => {
        test.skip(
            !process.env.E2E_BACKEND_UP,
            'Backend not running (set E2E_BACKEND_UP=1 with the API reachable to enable).',
        );

        await page.goto('/');
        await page.getByLabel(/email/i).fill(SEEDED_EMAIL);
        await page.getByLabel(/password/i).fill(SEEDED_PASS);
        await page.getByRole('button', { name: /sign in/i }).click();
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible({ timeout: 10_000 });
    });

    test('admin sees every nav link', async ({ page }) => {
        // Admin has all permissions; every nav item should render.
        for (const label of ['Dashboard', 'Journals', 'Customers', 'Invoices', 'POS', 'Reports']) {
            await expect(page.getByRole('link', { name: label })).toBeVisible();
        }
    });

    test('navigating to Journals shows the entries list', async ({ page }) => {
        await page.getByRole('link', { name: 'Journals' }).click();
        await expect(page.getByRole('heading', { name: 'Journal Entries' })).toBeVisible();
    });

    test('opening the new-JV editor reveals the balance banner', async ({ page }) => {
        await page.goto('/#/accounting/journals/new');
        await expect(page.getByRole('heading', { name: 'New Journal Entry' })).toBeVisible();
        // Empty editor → muted banner ("Enter debit and credit lines below...")
        await expect(page.getByText(/banner turns green when totals match/i)).toBeVisible();
    });

    test('sign out returns the user to login', async ({ page }) => {
        await page.getByRole('button', { name: /sign out/i }).click();
        await expect(page.getByRole('heading', { name: 'Philippine Accounting System' })).toBeVisible();
    });
});
