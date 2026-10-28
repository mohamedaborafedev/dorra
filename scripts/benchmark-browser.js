// Run through Playwright MCP browser_run_code with this filename after logging in.
async (page) => {
    const origin = 'http://127.0.0.1:8000';
    const results = [];
    for (const path of ['/dashboard', '/dashboard?search=Modern&category_id=3&status=published&page=2']) {
        const samples = [];
        for (let round = -2; round < 10; round++) {
            await page.goto(origin + path, { waitUntil: 'load' });
            if (!page.url().includes('/dashboard')) throw new Error('Log in before running this benchmark.');
            const metrics = await page.evaluate(() => {
                const n = performance.getEntriesByType('navigation')[0];
                return {
                    ttfb_ms: n.responseStart - n.requestStart,
                    response_ms: n.responseEnd - n.requestStart,
                    dom_content_loaded_ms: n.domContentLoadedEventEnd,
                    load_ms: n.loadEventEnd,
                    transfer_bytes: n.transferSize,
                };
            });
            if (round >= 0) samples.push(metrics);
        }
        results.push({ url: origin + path, samples });
    }
    return { browser: await page.evaluate(() => navigator.userAgent), warmups: 2, runs: 10, results };
}
