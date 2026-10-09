import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8095';

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './storage/e2e/results',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    workers: 4,
    retries: 0,
    reporter: [['list'], ['html', { outputFolder: './storage/e2e/report', open: 'never' }]],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.js/ },
        {
            name: 'desktop',
            testIgnore: [/auth\.setup\.js/, /mobile\.spec\.js/],
            dependencies: ['setup'],
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'mobile',
            testMatch: /mobile\.spec\.js/,
            dependencies: ['setup'],
            use: { ...devices['Pixel 7'] },
        },
    ],
});
