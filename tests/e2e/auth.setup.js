import { test as setup, expect } from '@playwright/test';
import { USERS, authFile, login } from './support.js';

for (const [role, creds] of Object.entries(USERS)) {
    setup(`authenticate ${role}`, async ({ page }) => {
        await login(page, creds);
        await expect(page).not.toHaveURL(/\/login/);
        await page.context().storageState({ path: authFile(role) });
    });
}
