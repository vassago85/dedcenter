import { test, expect, expectHealthyPage, authFile } from './support.js';

// One match walked through the real lifecycle by the org admin and a shooter:
// create → registration open → register + proof of payment → approve →
// squadding open → shooter picks a squad.
const ORG = '/org/royal-flush';
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');
const name = `E2E Lifecycle ${Date.now()}`;
let matchId;
let proofUrl;

test.describe.configure({ mode: 'serial' });

async function transition(page, label) {
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('region', { name: 'Match progress' }).getByRole('button', { name: label }).click();
    await expect(page.getByText(`Match status changed to ${label}.`)).toBeVisible();
}

test.describe('org admin sets up the match', () => {
    test.use({ storageState: authFile('org') });

    test('create a paid match and open registration', async ({ page }) => {
        await page.goto(`${ORG}/matches/create`);
        await page.locator('input[wire\\:model="name"]:visible').first().fill(name);
        await page.locator('input[wire\\:model="date"]:visible').first().fill('2027-04-18');
        await page.getByLabel('Entry Fee (ZAR)').first().fill('350');
        await page.getByRole('button', { name: 'Create Match' }).first().click();
        await page.waitForURL(/\/org\/royal-flush\/matches\/\d+/);
        matchId = Number(page.url().match(/matches\/(\d+)/)[1]);

        await page.goto(`${ORG}/matches/${matchId}`);
        await transition(page, 'Registration Open');
        await expectHealthyPage(page);
    });
});

test.describe('shooter registers', () => {
    test.use({ storageState: authFile('shooter') });

    test('register with equipment, then upload proof of payment', async ({ page }) => {
        await page.goto(`/matches/${matchId}`);
        const fields = {
            contact_number: '071 480 7251', caliber: '6.5 Creedmoor', bullet_brand_type: 'Hornady ELD-M',
            bullet_weight: '140gr', barrel_brand_length: 'Bartlein 26"', trigger_brand: 'TriggerTech',
            stock_chassis_brand: 'MPA', muzzle_brake_silencer_brand: 'Area 419', scope_brand_type: 'Vortex Razor',
            scope_mount_brand: 'Spuhr', bipod_brand: 'Harris',
        };
        for (const [model, value] of Object.entries(fields)) {
            await page.locator(`input[wire\\:model="${model}"]`).fill(value);
        }
        await page.getByRole('button', { name: 'Register for this Match' }).click();
        await expect(page.getByText('Payment Required')).toBeVisible();

        // A slow mobile upload: the submit must wait for the file, not race it.
        await page.route('**/upload-file**', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 2_000));
            await route.continue();
        });
        await page.locator('input[type="file"][wire\\:model="proofOfPayment"]').setInputFiles({ name: 'pop.png', mimeType: 'image/png', buffer: PNG });
        await page.getByRole('button', { name: /^Upload/ }).click();
        await expect(page.getByText('Your proof of payment is under review.')).toBeVisible();
        await expectHealthyPage(page);
    });
});

test.describe('org admin reviews the registration', () => {
    test.use({ storageState: authFile('org') });

    test('view the proof of payment and approve', async ({ page }) => {
        await page.goto(`${ORG}/registrations`);
        const row = page.getByRole('row', { name: new RegExp(name) });
        proofUrl = await row.getByRole('link', { name: 'View POP' }).getAttribute('href');
        const proof = await page.request.get(proofUrl);
        expect(proof.status()).toBe(200);
        expect(proof.headers()['content-type']).toContain('image/png');

        page.once('dialog', (dialog) => dialog.accept());
        await row.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Registration approved. Shooter added to match.')).toBeVisible();
    });

    test('add a squad and open squadding', async ({ page }) => {
        await page.goto(`${ORG}/matches/${matchId}/squadding`);
        await page.getByPlaceholder('e.g. Squad A').fill('Alpha');
        await page.getByRole('button', { name: 'Add Squad' }).click();
        await expect(page.getByText('Squad added.')).toBeVisible();

        await page.goto(`${ORG}/matches/${matchId}`);
        await transition(page, 'Registration Closed');
        await transition(page, 'Squadding Open');
    });
});

test.describe('shooter picks a squad', () => {
    test.use({ storageState: authFile('shooter') });

    test('own proof of payment is readable, then join Alpha', async ({ page }) => {
        expect((await page.request.get(proofUrl)).status()).toBe(200);

        await page.goto(`/matches/${matchId}`);
        await expect(page.getByText('Your registration is confirmed!')).toBeVisible();
        await page.getByRole('link', { name: /Pick your squad/ }).click();
        await page.waitForURL(new RegExp(`/matches/${matchId}/squadding$`));
        await page.getByRole('button', { name: /(Join|Switch to) Alpha/ }).click();
        await expect(page.getByText(/(Joined|Moved to) Alpha/)).toBeVisible();
        await expectHealthyPage(page);
    });
});

test.describe('proof of payment stays private', () => {
    test('guests cannot read it', async ({ page }) => {
        const res = await page.request.get(proofUrl, { maxRedirects: 0 });
        expect([302, 401, 403]).toContain(res.status());
    });
});
