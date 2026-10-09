import { test as base, expect } from '@playwright/test';

export const USERS = {
    admin: { email: 'admin@deadcenter.co.za', password: 'password' },
    org: { email: 'orgadmin@e2e.test', password: 'password' },
    shooter: { email: 'rf-demo-1@deadcenter.test', password: 'password' },
};

export const authFile = (role) => `storage/e2e/auth/${role}.json`;

// Known third-party noise that is not an app defect.
const IGNORED_CONSOLE = [
    /favicon\.ico/,
    /Failed to load resource: net::ERR_(BLOCKED_BY_CLIENT|NAME_NOT_RESOLVED|INTERNET_DISCONNECTED)/,
    /googletagmanager|google-analytics|doubleclick/,
];

/**
 * Every test gets a page that records console errors, uncaught exceptions,
 * same-origin 5xx responses and failed Livewire round-trips. The suite fails
 * the test if any were seen, so a page that "renders" but is broken under the
 * hood still goes red.
 */
export const test = base.extend({
    problems: async ({}, use) => {
        await use([]);
    },
    page: async ({ page, problems, baseURL }, use, testInfo) => {
        const origin = new URL(baseURL).origin;

        page.on('console', (msg) => {
            if (msg.type() !== 'error') return;
            const text = msg.text();
            if (IGNORED_CONSOLE.some((re) => re.test(text))) return;
            problems.push(`console: ${text} @ ${page.url()}`);
        });
        page.on('pageerror', (err) => problems.push(`pageerror: ${err.message} @ ${page.url()}`));
        page.on('response', (res) => {
            const url = res.url();
            if (!url.startsWith(origin)) return;
            const status = res.status();
            if (status >= 500) problems.push(`HTTP ${status}: ${res.request().method()} ${url}`);
            else if (url.includes('/livewire') && status >= 400 && status !== 419) {
                problems.push(`Livewire ${status}: ${url} (from ${page.url()})`);
            }
        });

        await use(page);

        if (problems.length) {
            await testInfo.attach('problems', { body: problems.join('\n'), contentType: 'text/plain' });
        }
        expect(problems, problems.join('\n')).toEqual([]);
    },
});

export { expect };

/** Asserts the page is not a Laravel error / exception screen. */
export async function expectHealthyPage(page) {
    const body = page.locator('body');
    await expect(body).not.toContainText(/Server Error|Whoops|ErrorException|Undefined (variable|array key|property)|Call to (a member function|undefined)|SQLSTATE|Internal Server Error/);
}

export async function login(page, { email, password }) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
}
