const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1600, height: 1200 } });

  await page.goto('http://127.0.0.1:8000', { waitUntil: 'domcontentloaded' });
  await page.click('[data-demo-login="admin@example.com|password"]');
  await page.waitForTimeout(1200);

  const metrics = await page.evaluate(() => {
    const rectData = (selector) => {
      const node = document.querySelector(selector);
      if (!node) {
        return null;
      }

      const rect = node.getBoundingClientRect();
      return {
        left: Math.round(rect.left),
        top: Math.round(rect.top),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
      };
    };

    return {
      mainGrid: rectData('.admin-main-grid'),
      analyticsGrid: rectData('.admin-analytics-grid'),
      queues: rectData('#adminQueues'),
      insights: rectData('#adminInsights'),
      mainColumnsGap: (() => {
        const left = document.querySelector('#adminQueues')?.getBoundingClientRect();
        const right = document.querySelector('#adminInsights')?.getBoundingClientRect();
        return left && right ? Math.round(right.left - left.right) : null;
      })(),
      horizontalOverflow: Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
    };
  });

  await page.screenshot({
    path: 'backend/storage/admin-layout-balanced.png',
    fullPage: true,
  });

  console.log(JSON.stringify(metrics, null, 2));
  await browser.close();
})();
