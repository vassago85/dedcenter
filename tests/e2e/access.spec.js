import { test, expect, authFile, login, USERS } from './support.js';

async function statusOf(page, path) {
    const res = await page.request.get(path, { maxRedirects: 0 });
    return { status: res.status(), location: res.headers()['location'] ?? '' };
}

test.describe('guest is kept out of authenticated areas', () => {
    for (const path of ['/dashboard', '/matches', '/settings', '/org/royal-flush/dashboard', '/admin/dashboard', '/score', '/scoreboard/16/export/standings']) {
        test(`guest ${path} → login`, async ({ page }) => {
            const { status, location } = await statusOf(page, path);
            expect(status).toBe(302);
            expect(location).toMatch(/\/login$/);
        });
    }
});

test.describe('shooter cannot reach staff surfaces', () => {
    test.use({ storageState: authFile('shooter') });

    for (const path of ['/admin/dashboard', '/admin/members', '/org/royal-flush/dashboard', '/org/royal-flush/matches/16', '/scoreboard/16/export/standings', '/org/royal-flush/matches/16/export/pdf-standings']) {
        test(`shooter ${path} is forbidden`, async ({ page }) => {
            const { status } = await statusOf(page, path);
            expect([403, 404]).toContain(status);
        });
    }

    test('shooter can open their own report for a match they shot', async ({ page }) => {
        const res = await page.goto('/matches/16/my-report');
        expect(res.status()).toBe(200);
    });
});

// Fresh login: the flash message is one-shot, so a session shared with
// parallel tests can have it consumed before this page renders.
test('shooter is bounced out of the scoring app with an explanation', async ({ page }) => {
    await login(page, USERS.shooter);
    await expect(page).toHaveURL(/\/dashboard/);
    await page.goto('/score');
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByText(/only available to match staff/i)).toBeVisible();
});

test.describe('org admin is confined to their organization', () => {
    test.use({ storageState: authFile('org') });

    for (const path of ['/admin/dashboard', '/admin/matches/16', '/org/clb-pretoria-prc/dashboard', '/org/alrha/matches/15']) {
        test(`org admin ${path} is forbidden`, async ({ page }) => {
            const { status } = await statusOf(page, path);
            expect([403, 404]).toContain(status);
        });
    }

    test('org admin cannot tamper an export URL onto another org match', async ({ page }) => {
        const { status } = await statusOf(page, '/org/royal-flush/matches/4/export/standings');
        expect([403, 404]).toContain(status);
    });

    test('org admin can export their own match', async ({ page }) => {
        const res = await page.request.get('/org/royal-flush/matches/16/export/standings');
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toMatch(/csv|text/);
    });
});
