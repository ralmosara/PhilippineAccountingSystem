import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright e2e config for PHA.
 *
 * Tests run against a fully-booted SPA + backend stack. CI starts both via
 * docker compose; locally `npm run dev` (frontend) + `php artisan serve`
 * (backend) need to be running on the configured ports.
 *
 * Strategy:
 *   - Smoke tests verify the SPA loads and login works.
 *   - Critical-path tests cover the BIR-compliance journeys that would
 *     fail an external audit (issue SI → post JV → file 2550M).
 *   - Tests run in parallel across browsers in CI; serial in local dev.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: !process.env.CI,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI ? [['html'], ['github']] : 'list',

    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:5173',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },

    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        // Firefox + Safari coverage added in a later batch once the chrome
        // suite is stable — having all three from day one triples local-dev
        // iteration time without much value while pages are still settling.
    ],

    expect: {
        // BIR-grade UIs render decimal amounts; visual diffs need decimal
        // tolerance because system fonts on different OSes render '₱1,234.56'
        // at sub-pixel different widths.
        toHaveScreenshot: { maxDiffPixelRatio: 0.01 },
    },
});
