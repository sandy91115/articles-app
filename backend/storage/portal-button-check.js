const { chromium } = require('playwright');
(async()=>{
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const result = {};
  await page.goto('http://localhost:8000', { waitUntil: 'domcontentloaded' });
  result.title = await page.title();
  result.loginButton = await page.locator('#loginButton').textContent();
  await page.click('[data-demo-login="admin@example.com|password"]');
  await page.waitForTimeout(800);
  result.email = await page.locator('#emailInput').inputValue();
  result.password = await page.locator('#passwordInput').inputValue();
  result.authMessageVisible = await page.locator('#authMessage').evaluate((el) => !el.classList.contains('hidden'));
  result.authMessage = await page.locator('#authMessage').textContent();
  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();
