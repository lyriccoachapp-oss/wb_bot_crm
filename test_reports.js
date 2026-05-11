const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage();
  
  page.on('console', msg => console.log('PAGE LOG:', msg.text()));
  page.on('pageerror', err => console.log('PAGE ERROR:', err.toString()));
  page.on('dialog', async dialog => {
    console.log('DIALOG:', dialog.message());
    await dialog.dismiss();
  });

  await page.goto('https://crm.workbangers.com/?route=login');
  await page.type('input[name="email"]', 'admin@workbangers.com');
  await page.type('input[name="password"]', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForNavigation();
  
  await page.goto('https://crm.workbangers.com/?route=reports');
  
  // Switch to full report tab
  await page.evaluate(() => {
    switchTab('full');
  });
  
  // Click 'Показать' button
  await page.evaluate(() => {
    loadFullReport(1);
  });
  
  await new Promise(r => setTimeout(r, 3000));
  
  await browser.close();
})();
