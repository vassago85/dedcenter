import { test, expect, authFile, expectHealthyPage } from './support.js';

async function expectNoHorizontalOverflow(page, path) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `${path} scrolls sideways by ${overflow}px`).toBeLessThanOrEqual(2);
}

const PUBLIC = ['/', '/events', '/events/16', '/scoreboard/16', '/scoreboard/17', '/scoreboard/2', '/scoreboard/3', '/login', '/register'];

for (const path of PUBLIC) {
    test(`mobile guest ${path}`, async ({ page }) => {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await expectHealthyPage(page);
        await expectNoHorizontalOverflow(page, path);
    });
}

test.describe('mobile shooter', () => {
    test.use({ storageState: authFile('shooter') });
    for (const path of ['/dashboard', '/matches', '/results', '/matches/16/my-report', '/settings']) {
        test(`mobile shooter ${path}`, async ({ page }) => {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            await expectHealthyPage(page);
            await expectNoHorizontalOverflow(page, path);
        });
    }
});

test.describe('mobile org admin (match day)', () => {
    test.use({ storageState: authFile('org') });
    for (const path of ['/org/royal-flush/dashboard', '/org/royal-flush/matches/17', '/org/royal-flush/registrations', '/org/royal-flush/matches/17/scoring']) {
        test(`mobile org ${path}`, async ({ page }) => {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            await expectHealthyPage(page);
            await expectNoHorizontalOverflow(page, path);
        });
    }
});
