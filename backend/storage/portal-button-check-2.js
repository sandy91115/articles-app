const { chromium } = require('playwright');
(async()=>{
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const result = {};
  await page.goto('http://localhost:8000', { waitUntil: 'domcontentloaded' });

  await page.click('[data-auth-switch="signup"]');
  await page.waitForTimeout(200);
  result.signupVisibleAfterSwitch = await page.locator('#signupView').evaluate((el) => !el.classList.contains('hidden'));

  await page.click('[data-auth-view="login"]');
  await page.waitForTimeout(200);
  result.loginVisibleAfterTab = await page.locator('#loginView').evaluate((el) => !el.classList.contains('hidden'));

  await page.fill('#emailInput', 'admin@example.com');
  await page.fill('#passwordInput', 'password');
  await page.click('#loginButton');
  await page.waitForTimeout(1200);

  result.appVisible = await page.locator('#appScreen').evaluate((el) => !el.classList.contains('hidden'));
  result.authHidden = await page.locator('#authScreen').evaluate((el) => el.classList.contains('hidden'));
  result.workspaceTitle = await page.locator('#workspaceTitle').textContent();
  result.roleBadge = await page.locator('#workspaceRoleBadge').textContent();
  result.refreshVisible = await page.locator('#refreshButton').evaluate((el) => !el.classList.contains('hidden'));
  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();
