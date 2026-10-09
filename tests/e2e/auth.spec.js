import { test, expect, USERS, login, expectHealthyPage } from './support.js';

const unique = () => `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

test.describe('authentication', () => {
    test('wrong password shows a clear error and stays on login', async ({ page }) => {
        await login(page, { email: USERS.shooter.email, password: 'not-the-password' });
        await expect(page.getByText(/these credentials do not match/i)).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });

    test('login is throttled after five failed attempts', async ({ page }) => {
        const email = `throttle-${unique()}@e2e.test`;
        await page.goto('/login');
        await page.getByLabel('Email').fill(email);
        for (let i = 0; i < 5; i++) {
            await page.getByLabel('Password').fill(`wrong-${i}`);
            await page.getByRole('button', { name: /sign in/i }).click();
            await expect(page.getByText(/these credentials do not match/i)).toBeVisible();
        }
        await page.getByLabel('Password').fill('wrong-final');
        await page.getByRole('button', { name: /sign in/i }).click();
        await expect(page.getByText(/too many login attempts/i)).toBeVisible();
    });

    for (const [role, landing] of [['shooter', /\/dashboard/], ['org', /\/(dashboard|org\/)/], ['admin', /\/(admin|dashboard)/]]) {
        test(`${role} can sign in and sign out`, async ({ page }) => {
            await login(page, USERS[role]);
            await expect(page).toHaveURL(landing);
            await expectHealthyPage(page);

            const visibleSignOut = page.locator('form[action$="/logout"] button:visible').first();
            if (await visibleSignOut.count()) {
                await visibleSignOut.click();
            } else {
                await page.locator('form[action$="/logout"]').first().evaluate((form) => form.requestSubmit());
            }
            await page.waitForURL((url) => !/\/(dashboard|admin|org)\b/.test(url.pathname));

            await page.goto('/dashboard');
            await expect(page).toHaveURL(/\/login/);
        });
    }

    test('registration validates and then creates an unverified account', async ({ page }) => {
        await page.goto('/register');
        await page.getByLabel('Name').fill('E2E Shooter');
        await page.getByLabel('Email').fill(`reg-${unique()}@e2e.test`);
        await page.getByLabel('Password', { exact: true }).fill('Sup3r-Secret-Pass!');
        await page.getByLabel('Confirm Password').fill('Different-Pass-123!');
        await page.getByRole('button', { name: /create account/i }).click();
        await expect(page.getByText(/confirmation does not match/i)).toBeVisible();
        await expect(page.getByText(/must accept the terms/i)).toBeVisible();

        await page.getByLabel('Confirm Password').fill('Sup3r-Secret-Pass!');
        await page.locator('input[type=checkbox][wire\\:model="accept_terms"]').check();
        await page.getByRole('button', { name: /create account/i }).click();
        await expect(page).toHaveURL(/\/verify-email/, { timeout: 30_000 });
        await expectHealthyPage(page);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/verify-email/);
    });

    test('registration rejects an email that already exists', async ({ page }) => {
        await page.goto('/register');
        await page.getByLabel('Name').fill('Dup');
        await page.getByLabel('Email').fill(USERS.shooter.email);
        await page.getByLabel('Password', { exact: true }).fill('Sup3r-Secret-Pass!');
        await page.getByLabel('Confirm Password').fill('Sup3r-Secret-Pass!');
        await page.locator('input[type=checkbox][wire\\:model="accept_terms"]').check();
        await page.getByRole('button', { name: /create account/i }).click();
        await expect(page.getByText(/already been taken/i)).toBeVisible();
    });

    test('forgot password accepts an email without leaking existence', async ({ page }) => {
        await page.goto('/forgot-password');
        await page.getByLabel('Email').fill(`nobody-${unique()}@e2e.test`);
        await page.getByRole('button').filter({ hasText: /send|reset|email/i }).first().click();
        await page.waitForLoadState('networkidle');
        await expectHealthyPage(page);
    });
});
