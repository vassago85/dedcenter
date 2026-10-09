import { readFileSync } from 'node:fs';
import { test, expect, expectHealthyPage, authFile, login } from './support.js';

// Routes the crawler can't reach by following links: token URLs, emailed
// links, app hand-offs and feature-flagged sections.

test('every URL in sitemap.xml resolves for a guest', async ({ request }) => {
    test.setTimeout(180_000);
    const res = await request.get('/sitemap.xml');
    expect(res.status()).toBe(200);
    expect(res.headers()['content-type']).toContain('xml');

    const locs = [...(await res.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => new URL(m[1]).pathname);
    expect(locs.length).toBeGreaterThan(10);
    const broken = [];
    for (const loc of locs) {
        const status = (await request.get(loc, { maxRedirects: 0 })).status();
        if (status !== 200) broken.push(`${status} ${loc}`);
    }
    expect(broken, broken.join('\n')).toEqual([]);
});

test('sponsor info is only readable with the shared token', async ({ page, request }) => {
    expect((await request.get('/sponsor-info/not-the-token')).status()).toBe(404);
    const res = await page.goto('/sponsor-info/e2e-sponsor-token');
    expect(res.status()).toBe(200);
    await expectHealthyPage(page);
});

test('password reset: emailed link sets a new password that signs in', async ({ page }) => {
    const email = 'reset@e2e.test';
    const password = `Reset-${Date.now()}-Aa1!`;

    await page.goto('/forgot-password');
    await page.getByLabel('Email').fill(email);
    await page.getByRole('button', { name: /reset|send/i }).click();
    await expect(page.getByText(/emailed you a password reset link/i)).toBeVisible({ timeout: 30_000 });

    const log = readFileSync('storage/logs/laravel.log', 'utf8').slice(-400_000).replace(/=\r?\n/g, '').replace(/=3D/g, '=');
    const links = [...log.matchAll(/\/reset-password\/([a-f0-9]{64})\?email=([^"&\s<]+)/g)]
        .filter((m) => decodeURIComponent(m[2]) === email);
    expect(links.length, 'reset email not found in laravel.log').toBeGreaterThan(0);
    const [, token] = links.at(-1);

    await page.goto(`/reset-password/${token}?email=${encodeURIComponent(email)}`);
    await page.getByLabel('New Password').fill(password);
    await page.getByLabel('Confirm Password').fill(password);
    await page.getByRole('button', { name: /reset/i }).click();
    await page.waitForURL(/\/login$/);

    await login(page, { email, password });
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
    await expectHealthyPage(page);
});

test.describe('app hand-off from the Android scoring app', () => {
    test('a scoring token signs a browser in, and only redirects in-app', async ({ browser, page }) => {
        const staff = await browser.newContext({ storageState: authFile('admin') });
        const scoring = await staff.newPage();
        await scoring.goto('/score');
        const token = await scoring.locator('meta[name="api-token"]').getAttribute('content');
        await staff.close();
        expect(token).toBeTruthy();

        await page.goto(`/app-login?token=${encodeURIComponent(token)}&redirect=//evil.example/phish`);
        expect(new URL(page.url()).host).toBe('127.0.0.1:8095');

        await page.goto(`/app-login?token=${encodeURIComponent(token)}&redirect=/admin/dashboard`);
        await page.waitForURL(/\/admin\/dashboard$/);
        await expectHealthyPage(page);
    });

    test('a bad token goes to the login page', async ({ page }) => {
        await page.goto('/app-login?token=1|not-a-real-token&redirect=/admin/dashboard');
        await page.waitForURL(/\/login$/);
    });
});

test.describe('org-scoped ELR exports', () => {
    test.use({ storageState: authFile('admin') });

    for (const kind of ['elr-rankings', 'pdf-elr-rankings']) {
        test(`${kind} downloads for the owning organisation`, async ({ request }) => {
            const res = await request.get(`/org/peregrine-elr-challenge/matches/13/export/${kind}`, { maxRedirects: 0 });
            expect(res.status()).toBe(200);
            expect(res.headers()['content-type']).toMatch(kind.startsWith('pdf') ? /pdf/ : /csv|text/);
        });
    }
});

test.describe('match books while the feature is switched off', () => {
    test.use({ storageState: authFile('admin') });

    for (const path of ['/admin/matches/2/matchbook', '/admin/matches/2/matchbook/edit', '/org/royal-flush/matches/2/matchbook/preview']) {
        test(`${path} answers 503, not a crash`, async ({ request }) => {
            expect((await request.get(path, { maxRedirects: 0 })).status()).toBe(503);
        });
    }
});
