import { test, expect, authFile } from './support.js';

test.use({ storageState: authFile('admin') });

const MATCHES = { standard: 17, completed: 16, prs: 2, prsClub: 4, elr: 3, elrPeregrine: 13, alrha: 15 };

function trackApi(page) {
    const failures = [];
    page.on('response', (res) => {
        const url = res.url();
        if (url.includes('/api/') && res.status() >= 400) {
            failures.push(`${res.status()} ${res.request().method()} ${new URL(url).pathname}`);
        }
    });
    return failures;
}

async function openSpa(page, hashPath = '') {
    await page.goto(`/score${hashPath}`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#app, [data-v-app]').first()).toBeAttached();
}

test('scoring home loads and lists matches', async ({ page }) => {
    const apiFailures = trackApi(page);
    await openSpa(page);
    await page.goto('/score/matches');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Royal Flush/);
    expect(apiFailures).toEqual([]);
});

for (const [kind, id] of Object.entries(MATCHES)) {
    test(`scoring SPA routes for ${kind} match ${id}`, async ({ page }) => {
        const apiFailures = trackApi(page);
        const views = ['', '/scoring', '/scoreboard', '/matrix', '/shooters'];
        if (kind.startsWith('elr')) views.push('/rankings');

        for (const view of views) {
            await page.goto(`/score/${id}${view}`);
            await page.waitForLoadState('networkidle');
            const text = await page.locator('body').innerText();
            expect(text, `/score/${id}${view} rendered blank`).not.toEqual('');
            expect(text).not.toMatch(/Server Error|SQLSTATE|undefined is not|Cannot read properties/);
        }
        expect(apiFailures, apiFailures.join('\n')).toEqual([]);
    });
}

test('PRS timed stage: shots → time prompt → stage completes on the server', async ({ page }) => {
    const apiFailures = trackApi(page);
    await page.goto(`/score/${MATCHES.prs}/scoring`);
    await page.getByRole('button', { name: 'Start Scoring' }).click();
    await page.getByRole('button', { name: /^Relay 2/ }).click();
    await page.getByRole('button', { name: /^Stage 1 —/ }).click();
    await page.getByRole('button', { name: /not started/i }).first().click();

    for (const result of ['HIT', 'MISS', 'HIT', 'HIT']) {
        await page.getByRole('button', { name: new RegExp(`^${result}$`) }).click();
    }
    await page.getByRole('button', { name: 'COMPLETE STAGE' }).click();

    await expect(page.getByRole('heading', { name: 'Enter Stage Time' })).toBeVisible();
    await page.getByPlaceholder('e.g. 105.00').fill('87.25');
    const completed = page.waitForResponse((res) => res.request().method() === 'POST' && /\/api\/matches\/\d+\/stages\/\d+\/score$/.test(res.url()));
    await page.getByRole('button', { name: 'Confirm' }).click();
    expect((await completed).status()).toBeLessThan(300);
    await expect(page.getByText(/Time is required/)).toHaveCount(0);
    expect(apiFailures, apiFailures.join('\n')).toEqual([]);
});

test('ELR team stage: pick lead-off → hit → shot and team stage reach the server', async ({ page }) => {
    const apiFailures = trackApi(page);
    await page.goto(`/score/${MATCHES.elrPeregrine}/scoring`);
    await page.getByRole('button', { name: /^Warrior/ }).click();
    await page.getByRole('button', { name: /^2 Brothers Arms/ }).click();
    await page.getByRole('button', { name: /leads off/i }).first().click();

    const shot = page.waitForResponse((res) => res.request().method() === 'POST' && res.url().endsWith(`/api/matches/${MATCHES.elrPeregrine}/elr-shots`));
    await page.getByRole('button', { name: /^HIT$/ }).click();
    expect((await shot).status()).toBeLessThan(300);
    expect(apiFailures, apiFailures.join('\n')).toEqual([]);
});

test('standard match: roll call → hit + miss → synced to the server', async ({ page }) => {
    const apiFailures = trackApi(page);
    const matchId = 1;

    await page.goto(`/score/${matchId}/scoring`);
    await page.getByRole('button', { name: /Relay 4 Delta/ }).click();
    await expect(page).toHaveURL(/\/rollcall$/);
    await page.getByRole('button', { name: 'Start Scoring' }).click();
    await expect(page).toHaveURL(/\/scoring$/);

    await page.getByRole('button', { name: /^HIT$/ }).click();
    await page.getByRole('button', { name: /^MISS$/ }).click();

    const synced = page.waitForResponse((res) => res.url().endsWith(`/api/matches/${matchId}/scores`) && res.request().method() === 'POST');
    await page.getByRole('button', { name: /2 pending/ }).click();
    expect((await synced).status()).toBe(200);
    await expect(page.getByRole('button', { name: /pending/ })).toHaveCount(0);

    const scores = await page.evaluate(async (id) => {
        const token = document.querySelector('meta[name="api-token"]').content;
        const res = await fetch(`/api/matches/${id}`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } });
        const body = await res.json();
        return (body.data ?? body).scores ?? [];
    }, matchId);
    expect(scores.some((s) => s.is_hit === true || s.is_hit === 1)).toBe(true);
    expect(scores.some((s) => s.is_hit === false || s.is_hit === 0)).toBe(true);
    expect(apiFailures, apiFailures.join('\n')).toEqual([]);
});
