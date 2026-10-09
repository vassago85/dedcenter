import { writeFileSync } from 'node:fs';
import { test, expect, authFile } from './support.js';

const MATCH_IDS = Array.from({ length: 17 }, (_, i) => i + 1);

const SEEDS = {
    guest: [
        '/', '/features', '/scoring', '/sponsorships', '/advertise', '/sponsor-marketplace', '/offline',
        '/setup', '/privacy', '/terms', '/events', '/badges-preview', '/login', '/register', '/forgot-password',
        '/leaderboard/royal-flush', '/leaderboard/peregrine-elr-challenge', '/leaderboard/alrha',
        '/p/royal-flush', '/p/royal-flush/matches', '/p/royal-flush/leaderboard', '/p/alrha',
        '/p/peregrine-elr-challenge/leaderboard', '/shooters/185',
        ...MATCH_IDS.flatMap((id) => [`/events/${id}`, `/scoreboard/${id}`]),
        '/scoreboard/16/full-match-report', '/scoreboard/2/full-match-report',
    ],
    shooter: [
        '/dashboard', '/welcome', '/matches', '/results', '/browse-events', '/equipment', '/organizations',
        '/organizations/create', '/settings', '/notifications', '/settings/notifications', '/claim',
        '/matches/16', '/matches/16/my-report',
    ],
    org: [
        '/org/royal-flush/dashboard', '/org/royal-flush/matches', '/org/royal-flush/matches/create',
        '/org/royal-flush/registrations', '/org/royal-flush/admins', '/org/royal-flush/clubs',
        '/org/royal-flush/settings', '/org/royal-flush/portal-sponsors',
        ...[1, 2, 16, 17].flatMap((id) => [
            `/org/royal-flush/matches/${id}`, `/org/royal-flush/matches/${id}/edit`,
            `/org/royal-flush/matches/${id}/squadding`, `/org/royal-flush/matches/${id}/scoring`,
            `/org/royal-flush/matches/${id}/reports`, `/org/royal-flush/matches/${id}/side-bet`,
            `/org/royal-flush/matches/${id}/side-bet-report`,
        ]),
    ],
    admin: [
        '/admin/dashboard', '/admin/organizations', '/admin/members', '/admin/matches', '/admin/matches/create',
        '/admin/sponsors', '/admin/advertising', '/admin/sponsor-assignments', '/admin/sponsor-info',
        '/admin/contact-submissions', '/admin/registrations', '/admin/seasons', '/admin/homepage',
        '/admin/settings', '/admin/shooter-claims',
        ...MATCH_IDS.flatMap((id) => [
            `/admin/matches/${id}`, `/admin/matches/${id}/edit`, `/admin/matches/${id}/squadding`,
            `/admin/matches/${id}/scoring`, `/admin/matches/${id}/reports`,
        ]),
    ],
};

const SKIP = [/\/logout/, /\/mode-switch/, /\/app-login/, /\/score(\/|$)/, /\/reset-password\//, /\/sponsor-info\//];
const FILE_LIKE = /\/(export|download)\/|\/download$|\/downloads\/|\.(pdf|csv|apk)$|\/sitemap\.xml/;
const MAX_PAGES = 260;
const MAX_PER_PATTERN = 4;

const pattern = (path) => path.replace(/\/\d+(?=\/|$)/g, '/:id');

function normalise(href, origin) {
    try {
        const url = new URL(href, origin);
        if (url.origin !== origin) return null;
        url.hash = '';
        return url.pathname + url.search;
    } catch {
        return null;
    }
}

for (const role of Object.keys(SEEDS)) {
    test.describe(`crawl as ${role}`, () => {
        if (role !== 'guest') test.use({ storageState: authFile(role) });

        test(`every reachable page renders cleanly (${role})`, async ({ page, baseURL, problems }) => {
            test.setTimeout(20 * 60_000);
            const origin = new URL(baseURL).origin;
            const queue = [...SEEDS[role]];
            const seen = new Set(queue);
            const perPattern = new Map();
            const failures = [];
            const visitedPaths = [];
            let visited = 0;

            while (queue.length && visited < MAX_PAGES) {
                const path = queue.shift();
                const key = pattern(path.split('?')[0]);
                const count = perPattern.get(key) ?? 0;
                if (count >= MAX_PER_PATTERN && !SEEDS[role].includes(path)) continue;
                perPattern.set(key, count + 1);
                visited++;
                visitedPaths.push(path);

                if (FILE_LIKE.test(path)) {
                    const res = await page.request.get(path, { maxRedirects: 0 }).catch((e) => ({ status: () => 0, err: e }));
                    if (res.status() >= 400 || res.status() === 0) failures.push(`${res.status()} file ${path}`);
                    continue;
                }

                const before = problems.length;
                let res;
                try {
                    res = await page.goto(path, { waitUntil: 'domcontentloaded' });
                    await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
                } catch (e) {
                    failures.push(`navigation error ${path}: ${e.message.split('\n')[0]}`);
                    continue;
                }

                const status = res?.status() ?? 0;
                if (status >= 400) failures.push(`${status} ${path}`);

                const bodyText = await page.locator('body').innerText().catch(() => '');
                const fatal = bodyText.match(/Server Error|Whoops|ErrorException|Undefined (variable|array key|property)|Call to (a member function|undefined)|SQLSTATE|Attempt to read property/);
                if (fatal) failures.push(`error text "${fatal[0]}" on ${path}`);

                if (problems.length > before) {
                    failures.push(...problems.splice(before).map((p) => `${p} [while on ${path}]`));
                }

                const hrefs = await page.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')));
                for (const href of hrefs) {
                    const next = normalise(href, origin);
                    if (!next || seen.has(next) || SKIP.some((re) => re.test(next))) continue;
                    if (role !== 'guest' && /^\/(login|register|forgot-password)/.test(next)) continue;
                    seen.add(next);
                    queue.push(next);
                }
            }

            writeFileSync(`storage/e2e/visited-${role}.json`, JSON.stringify(visitedPaths, null, 2));
            test.info().annotations.push({ type: 'pages visited', description: String(visited) });
            expect(failures, `\n${failures.join('\n')}`).toEqual([]);
        });
    });
}
