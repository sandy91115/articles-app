const { chromium } = require('playwright');

const TARGET_URL = 'http://127.0.0.1:8000';

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
    const consoleErrors = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });

    page.on('pageerror', (error) => {
        consoleErrors.push(error.message);
    });

    await page.goto(TARGET_URL, { waitUntil: 'networkidle' });
    await page.waitForSelector('#authTabLogin');

    const isVisible = async (selector) =>
        page.locator(selector).evaluate((element) => !element.classList.contains('hidden'));

    const initialState = {
        login: await isVisible('#loginView'),
        signup: await isVisible('#signupView'),
        verify: await isVisible('#verifyView'),
    };

    await page.click('#authTabSignup');
    await page.waitForTimeout(100);

    const signupState = {
        login: await isVisible('#loginView'),
        signup: await isVisible('#signupView'),
        verify: await isVisible('#verifyView'),
    };

    await page.click('#authTabVerify');
    await page.waitForTimeout(100);

    const verifyState = {
        login: await isVisible('#loginView'),
        signup: await isVisible('#signupView'),
        verify: await isVisible('#verifyView'),
    };

    await page.click('#verifyView [data-auth-switch="login"]');
    await page.waitForTimeout(100);

    const switchedBackState = {
        login: await isVisible('#loginView'),
        signup: await isVisible('#signupView'),
        verify: await isVisible('#verifyView'),
    };

    console.log(
        JSON.stringify(
            {
                initialState,
                signupState,
                verifyState,
                switchedBackState,
                consoleErrors,
            },
            null,
            2,
        ),
    );

    if (!initialState.login || initialState.signup || initialState.verify) {
        throw new Error('Unexpected initial auth view state.');
    }

    if (signupState.login || !signupState.signup || signupState.verify) {
        throw new Error('Signup tab did not activate correctly.');
    }

    if (verifyState.login || verifyState.signup || !verifyState.verify) {
        throw new Error('Verify tab did not activate correctly.');
    }

    if (!switchedBackState.login || switchedBackState.signup || switchedBackState.verify) {
        throw new Error('Inline auth switch did not return to login.');
    }

    if (consoleErrors.length) {
        throw new Error(`Console errors detected: ${consoleErrors.join(' | ')}`);
    }

    await browser.close();
})();
