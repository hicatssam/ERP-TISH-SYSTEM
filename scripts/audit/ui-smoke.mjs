/**
 * TISH UI read-only smoke audit. Only login submits a POST.
 * Requires playwright, audit-routes.json, TISH_TEST_LOGIN / TISH_TEST_PASSWORD.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const baseURL = (process.env.TISH_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const login = process.env.TISH_TEST_LOGIN;
const password = process.env.TISH_TEST_PASSWORD;
const role = (process.env.TISH_TEST_ROLE || 'test-user').replace(/[^\w-]/g, '_');
const inputPath = path.resolve(process.env.TISH_ROUTES_FILE || 'audit-routes.json');
const outDir = path.resolve(process.env.TISH_AUDIT_OUTPUT || `audit/browser/${role}`);
const limit = Math.max(1, Math.min(500, Number(process.env.TISH_AUDIT_LIMIT || '50')));
const viewports = [
  { name: 'desktop', width: 1366, height: 768 },
  { name: 'mobile', width: 390, height: 844 },
  { name: 'small-mobile', width: 360, height: 800 },
];
const relevant = /^(restaurant(?:\/|$)|kitchen(?:\/|$)|kds(?:\/|$)|dashboard$|orders(?:\/|$)|menu(?:\/|$)|products(?:\/|$)|categories(?:\/|$)|customers(?:\/|$)|invoices(?:\/|$)|payments(?:\/|$)|payment-methods(?:\/|$)|cash-sessions(?:\/|$)|reports(?:\/|$)|inventory(?:\/|$)|stock-(?:requests|transfers|counts)(?:\/|$)|recipes(?:\/|$)|settings\/payment-checkout$|settings\/print-branding$)/;
const excluded = /(?:cake|sweets|showroom|tomorrow-preparation|bakery)/i;
const nonPage = /(?:\/feed|\/catalog|\/status-feed|\/cart-availability|\/menu-availability|\/receipt-search|\/qr-orders|\/drafts|\/count|\/products|\/tables|\/payment-options|\/queue-status|\/live\/list|\/pdf|\/print|\/export|\/xlsx|\/csv|\/image|\/icon|\/manifest|\/search|\/unread-count|\/status|\/proof|\/push\/public-key)$/;

if (!login || !password || login === 'TEST_USERNAME' || password === 'TEST_PASSWORD') {
  console.error('Set real credentials for a dedicated non-production test account in TISH_TEST_LOGIN / TISH_TEST_PASSWORD.');
  process.exit(2);
}
if (!fs.existsSync(inputPath)) {
  console.error(`Route inventory not found: ${inputPath}. Run php artisan route:list --json > audit-routes.json`);
  process.exit(2);
}
const routes = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
if (!Array.isArray(routes)) throw new Error('Expected Laravel route:list --json array');
const eligible = routes.filter(r =>
  String(r.method).includes('GET') &&
  !String(r.uri).includes('{') &&
  relevant.test(String(r.uri)) &&
  !excluded.test(String(r.uri)) &&
  !nonPage.test(String(r.uri)) &&
  !/\.(?:png|pdf|xlsx|csv|webmanifest)$/.test(String(r.uri))
).sort((a, b) => String(a.uri).localeCompare(String(b.uri)));
const selected = eligible.slice(0, limit);
fs.mkdirSync(outDir, { recursive: true });
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: viewports[0], locale: 'ar-PS' });
const loginPage = await context.newPage();
try {
  const loginResponse = await loginPage.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded', timeout: 20000 });
  if (!loginResponse || loginResponse.status() >= 400) throw new Error(`Login page HTTP ${loginResponse?.status()}`);
  await loginPage.locator('input[name="login"]').fill(login);
  await loginPage.locator('input[name="password"]').fill(password);
  await Promise.all([
    loginPage.waitForLoadState('domcontentloaded').catch(() => {}),
    loginPage.locator('button[type="submit"]').click(),
  ]);
  await loginPage.waitForTimeout(500);
  if (new URL(loginPage.url()).pathname === '/login') throw new Error('Login did not complete; check test account and its location.');
} catch (e) {
  await loginPage.screenshot({ path: path.join(outDir, 'login-failure.png'), fullPage: true }).catch(() => {});
  console.error(`Login failed: ${e.message}. Check screenshot and local server.`);
  await browser.close();
  process.exit(3);
}
await loginPage.close();
const results = [];
for (const viewport of viewports) {
  for (const r of selected) {
    const slug = `${viewport.name}-${String(r.name || r.uri).replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 75)}`;
    const page = await context.newPage();
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    const jsErrors = [];
    const failedRequests = [];
    page.on('pageerror', e => jsErrors.push(e.message.slice(0, 300)));
    page.on('requestfailed', req => failedRequests.push({ url: req.url().slice(0, 200), reason: req.failure()?.errorText }));
    const result = { role, viewport: viewport.name, route: r.name, uri: r.uri, status: 'NOT_TESTED', httpStatus: null,
      finalPath: null, pageTitle: null, horizontalOverflowPx: null, brokenLocalImages: [], jsErrors, failedRequests, screenshot: null };
    try {
      const response = await page.goto(`${baseURL}/${r.uri}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(300);
      result.httpStatus = response?.status() ?? null;
      result.finalPath = new URL(page.url()).pathname;
      result.pageTitle = await page.title();
      const geometry = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
        brokenLocalImages: [...document.images].filter(img => !img.complete || img.naturalWidth === 0)
          .filter(img => img.src && new URL(img.src).origin === location.origin).map(img => img.src.slice(0, 200)).slice(0, 20),
      }));
      result.horizontalOverflowPx = Math.max(0, geometry.documentWidth - geometry.viewportWidth);
      result.brokenLocalImages = geometry.brokenLocalImages;
      if (result.finalPath === '/login') result.status = 'BLOCKED_AUTH';
      else if (result.httpStatus === 403) result.status = 'DENIED_403';
      else if (result.httpStatus === 404) result.status = 'MISSING_404';
      else if (!response || result.httpStatus >= 500) result.status = 'SERVER_ERROR';
      else if (result.horizontalOverflowPx > 8 || result.brokenLocalImages.length || jsErrors.length) result.status = 'UI_ISSUE';
      else result.status = 'SMOKE_PASS';
      if (result.status !== 'SMOKE_PASS') {
        result.screenshot = `${slug}.png`;
        await page.screenshot({ path: path.join(outDir, result.screenshot), fullPage: true });
      }
    } catch (e) {
      result.status = 'BLOCKED_OR_ERROR';
      result.error = e.message.slice(0, 500);
      result.screenshot = `${slug}.png`;
      await page.screenshot({ path: path.join(outDir, result.screenshot), fullPage: true, timeout: 6000 }).catch(() => {});
    } finally { results.push(result); await page.close(); }
    console.log(`${viewport.name.padEnd(13)} ${result.status.padEnd(18)} ${r.uri}`);
  }
}
await browser.close();
const counts = results.reduce((m, r) => (m[r.status] = (m[r.status] || 0) + 1, m), {});
const summary = { baseURL, role, createdAt: new Date().toISOString(), inventoryTotal: routes.length,
  eligibleStaticPages: eligible.length, selectedPages: selected.length, testedViewportVisits: results.length,
  viewports, counts, disclaimer: 'Read-only smoke test (except login). Does not validate write operations or dynamic detail URLs.' };
fs.writeFileSync(path.join(outDir, 'results.json'), JSON.stringify({ summary, results }, null, 2));
const lines = [
  '# TISH UI smoke audit', '', `Role: ${role}`, `Selected static pages: ${selected.length}/${eligible.length}`,
  `Viewport visits: ${results.length}`, '', '| Status | Count |', '|---|---:|',
  ...Object.entries(counts).map(([key, value]) => `| ${key} | ${value} |`), '',
  '## Findings', '',
  ...results.filter(r => r.status !== 'SMOKE_PASS').map(r => `- ${r.viewport}: \`${r.uri}\` — **${r.status}** (HTTP ${r.httpStatus ?? 'n/a'}, overflow ${r.horizontalOverflowPx ?? 'n/a'}px) ${r.screenshot || ''}`),
  '', 'Passing smoke tests do not establish full functionality or permission correctness.',
];
fs.writeFileSync(path.join(outDir, 'report.md'), lines.join('\n'));
console.log(`\nWrote ${path.join(outDir, 'report.md')} and results.json`);
if (Object.keys(counts).some(x => ['UI_ISSUE', 'SERVER_ERROR', 'MISSING_404', 'BLOCKED_OR_ERROR'].includes(x))) process.exitCode = 1;
