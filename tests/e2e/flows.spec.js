import { test, expect, authFile, expectHealthyPage } from './support.js';

test.describe('public flows', () => {
    test('events listing filters live by search', async ({ page }) => {
        await page.goto('/events');
        const search = page.getByPlaceholder('Match name or location...');
        await search.fill('Peregrine');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).toContainText(/Peregrine/);
        await expect(page.locator('body')).not.toContainText('CLB Pretoria — Club Match');

        await search.fill('zzz-no-such-match');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).not.toContainText(/Peregrine ELR Challenge — Round/);
        await expectHealthyPage(page);
    });

    test('events listing selects work without errors', async ({ page }) => {
        await page.goto('/events');
        for (const select of await page.locator('select[wire\\:model\\.live]').all()) {
            const values = await select.locator('option').evaluateAll((os) => os.map((o) => o.value).filter(Boolean));
            for (const value of values.slice(0, 3)) {
                await select.selectOption(value);
                await page.waitForLoadState('networkidle');
            }
            await select.selectOption('');
        }
        await expectHealthyPage(page);
    });

    for (const id of [1, 2, 3, 5, 15, 16, 17]) {
        test(`scoreboard ${id}: every tab and toggle renders, live polling stays clean`, async ({ page }) => {
            await page.goto(`/scoreboard/${id}`);
            await expectHealthyPage(page);

            const tabs = page.locator('main button[wire\\:click], main [role=tab], main button[x-on\\:click], main button[\\@click]');
            const count = Math.min(await tabs.count(), 25);
            for (let i = 0; i < count; i++) {
                const tab = tabs.nth(i);
                if (!(await tab.isVisible())) continue;
                const label = (await tab.innerText()).trim();
                if (/claim|delete|remove|reset|logout|sign out|print|share|copy/i.test(label)) continue;
                await tab.click({ timeout: 5_000 }).catch(() => {});
                await page.waitForLoadState('networkidle').catch(() => {});
                await expectHealthyPage(page);
            }

            // Let at least one wire:poll cycle run.
            await page.waitForTimeout(6_000);
        });
    }

    test('legacy /live link forwards to the scoreboard', async ({ page }) => {
        await page.goto('/live/17');
        await expect(page).toHaveURL(/\/scoreboard\/17$/);
    });
});

test.describe('shooter flows', () => {
    test.use({ storageState: authFile('shooter') });

    test('dashboard shows next steps and a find-a-match path', async ({ page }) => {
        await page.goto('/dashboard');
        await expectHealthyPage(page);
        await expect(page.getByRole('link', { name: /find a match|browse/i }).first()).toBeVisible();
    });

    test('results lists the completed match and filters by year', async ({ page }) => {
        await page.goto('/results');
        await expect(page.locator('body')).toContainText('Royal Flush — April Completed');
        const yearChip = page.getByRole('button', { name: /^20\d\d$/ }).first();
        if (await yearChip.count()) {
            await yearChip.click();
            await page.waitForLoadState('networkidle');
            await expect(page.locator('body')).toContainText('Royal Flush — April Completed');
        }
        await page.getByPlaceholder(/search/i).first().fill('nothing-matches-this');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).not.toContainText('Royal Flush — April Completed');
    });

    test('settings: profile name saves and wrong current password is rejected', async ({ page }) => {
        await page.goto('/settings');
        const name = page.locator('input[wire\\:model="name"]');
        await name.fill('RF Demo Shooter E2E');
        await name.locator('xpath=ancestor::form').getByRole('button').first().click();
        await page.waitForLoadState('networkidle');
        await page.reload();
        await expect(page.locator('input[wire\\:model="name"]')).toHaveValue('RF Demo Shooter E2E');

        await page.locator('input[wire\\:model="current_password"]').fill('definitely-wrong');
        await page.locator('input[wire\\:model="password"]').fill('N3w-Password-123!');
        await page.locator('input[wire\\:model="password_confirmation"]').fill('N3w-Password-123!');
        await page.locator('input[wire\\:model="current_password"]').locator('xpath=ancestor::form').getByRole('button').first().click();
        await expect(page.getByText(/password is incorrect|current password/i).first()).toBeVisible();
    });
});

test.describe('org admin flows', () => {
    test.use({ storageState: authFile('org') });

    test('create a draft match, then the hub shows it as Draft', async ({ page }) => {
        const name = `E2E Draft ${Date.now()}`;
        await page.goto('/org/royal-flush/matches/create');
        await page.locator('input[wire\\:model="name"]:visible').first().fill(name);
        await page.locator('input[wire\\:model="date"]:visible').first().fill('2027-03-14');
        await page.getByRole('button', { name: 'Create Match' }).first().click();
        await page.waitForURL(/\/org\/royal-flush\/matches\/\d+/);
        await expectHealthyPage(page);

        await page.goto('/org/royal-flush/matches');
        await expect(page.locator('body')).toContainText(name);
        await page.getByRole('row', { name: new RegExp(name) }).getByRole('link', { name: 'Open' }).click();
        await page.waitForURL(/\/org\/royal-flush\/matches\/\d+$/);
        await expect(page.locator('body')).toContainText(/draft/i);
        await expectHealthyPage(page);
    });

    test('create match rejects a missing name', async ({ page }) => {
        await page.goto('/org/royal-flush/matches/create');
        const nameInput = page.locator('input[wire\\:model="name"]:visible').first();
        await nameInput.evaluate((el) => el.removeAttribute('required'));
        await page.locator('input[wire\\:model="date"]:visible').first().evaluate((el) => el.removeAttribute('required'));
        await page.getByRole('button', { name: 'Create Match' }).first().click();
        await expect(page.getByText(/name field is required/i).first()).toBeVisible();
    });

    test('match hub surfaces status and a primary action for every lifecycle state', async ({ page }) => {
        for (const id of [1, 16, 17]) {
            await page.goto(`/org/royal-flush/matches/${id}`);
            await expectHealthyPage(page);
            await expect(page.locator('body')).toContainText(/active|completed|draft|squadding|registration|ready/i);
        }
    });
});

test.describe('platform admin flows', () => {
    test.use({ storageState: authFile('admin') });

    test('members search narrows the table', async ({ page }) => {
        await page.goto('/admin/members');
        const search = page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="search"]').first();
        await search.fill('orgadmin@e2e');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).toContainText('orgadmin@e2e.test');
        await expect(page.locator('body')).not.toContainText('rf-demo-1@deadcenter.test');
    });

    test('mode switcher moves between admin, org and shooter modes', async ({ page }) => {
        await page.goto('/admin/dashboard');
        const forms = page.locator('form[action$="/mode-switch"]');
        const modes = await forms.locator('input[name="mode"], button[name="mode"]').evaluateAll((els) => [...new Set(els.map((e) => e.value))]);
        expect(modes.length).toBeGreaterThan(0);
        for (const mode of modes) {
            await page.goto('/admin/dashboard');
            const form = page.locator(`form[action$="/mode-switch"]:has([name="mode"][value="${mode}"])`).first();
            await form.evaluate((f, m) => {
                const btn = f.querySelector(`button[name="mode"][value="${m}"]`);
                btn ? f.requestSubmit(btn) : f.requestSubmit();
            }, mode);
            await page.waitForLoadState('networkidle');
            await expectHealthyPage(page);
        }
    });

    test('admin match hubs render for every scoring type and status', async ({ page }) => {
        for (const id of [1, 2, 3, 4, 5, 6, 15, 16, 17]) {
            const res = await page.goto(`/admin/matches/${id}`);
            expect(res.status(), `/admin/matches/${id}`).toBe(200);
            await expectHealthyPage(page);
        }
    });
});
