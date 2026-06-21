import { chromium } from 'playwright';

const readStdin = async () => {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
};

const truncate = (value, maxLength) => {
    if (typeof value !== 'string') {
        return null;
    }

    if (value.length <= maxLength) {
        return value;
    }

    const marker = ' [truncated]';

    return `${value.slice(0, Math.max(0, maxLength - marker.length)).trimEnd()}${marker}`;
};

const normalizeText = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    return value.replace(/\s+/g, ' ').trim();
};

const collectFrameText = async (page) => {
    const textBlocks = [];
    const seen = new Set();

    for (const frame of page.frames()) {
        const text = normalizeText(await frame.locator('body').innerText({ timeout: 1000 }).catch(() => ''));

        if (text === '' || seen.has(text)) {
            continue;
        }

        seen.add(text);
        textBlocks.push(text);
    }

    return textBlocks.join('\n\n');
};

const collectTitle = async (page) => {
    const pageTitle = (await page.title()).trim();

    if (pageTitle !== '') {
        return pageTitle;
    }

    for (const frame of page.frames()) {
        const frameTitle = (await frame.title().catch(() => '')).trim();

        if (frameTitle !== '') {
            return frameTitle;
        }
    }

    return '';
};

const fail = (message) => {
    process.stdout.write(JSON.stringify({
        ok: false,
        error: message,
    }));
    process.exit(1);
};

let input;

try {
    input = JSON.parse(await readStdin());
} catch (error) {
    fail(`Invalid JSON input: ${error.message}`);
}

if (!input?.url) {
    fail('Missing url.');
}

const timeoutMs = Math.max(1000, Number(input.timeoutMs ?? 12000));
const settleMs = Math.max(0, Number(input.settleMs ?? 1500));
const maxTextChars = Math.max(1000, Number(input.maxTextChars ?? 50000));
const maxHtmlChars = Math.max(1000, Number(input.maxHtmlChars ?? 100000));

let browser;
const startedAt = Date.now();

try {
    browser = await chromium.launch({
        headless: true,
        args: [
            '--disable-dev-shm-usage',
            '--no-sandbox',
        ],
    });

    const context = await browser.newContext({
        acceptDownloads: false,
        bypassCSP: false,
        ignoreHTTPSErrors: true,
        javaScriptEnabled: true,
        userAgent: input.userAgent || undefined,
        viewport: {
            width: Math.max(320, Number(input.viewport?.width ?? 1366)),
            height: Math.max(240, Number(input.viewport?.height ?? 900)),
        },
    });

    const page = await context.newPage();
    page.setDefaultTimeout(timeoutMs);
    page.setDefaultNavigationTimeout(timeoutMs);

    await page.goto(input.url, {
        waitUntil: 'domcontentloaded',
        timeout: timeoutMs,
    });

    if (settleMs > 0) {
        await page.waitForTimeout(settleMs);
    }

    const title = await collectTitle(page);
    const bodyText = await collectFrameText(page);
    const html = await page.locator('html').evaluate((node) => node.outerHTML).catch(() => '');

    process.stdout.write(JSON.stringify({
        ok: true,
        title: truncate(title, 512),
        text: truncate(bodyText, maxTextChars),
        html: truncate(html, maxHtmlChars),
        durationMs: Date.now() - startedAt,
        finalUrl: page.url(),
    }));
} catch (error) {
    fail(error instanceof Error ? error.message : String(error));
} finally {
    if (browser) {
        await browser.close().catch(() => {});
    }
}
