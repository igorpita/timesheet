const { chromium } = require('/Users/igorpita/dev/node_modules/playwright');
const path = require('path');

async function main() {
  const browser = await chromium.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2
  });
  const page = await context.newPage();

  console.log('1. Acessando login do Nextcloud local...');
  await page.goto('http://localhost:8080/index.php/login');
  await page.waitForTimeout(2000);

  const userInput = page.locator('input[type="text"]').first();
  const passInput = page.locator('input[type="password"]').first();
  await userInput.fill('admin');
  await passInput.fill('admin_secret');
  
  const submitBtn = page.locator('button:has-text("Entrar")').first();
  await submitBtn.click();
  await page.waitForTimeout(3000);

  console.log('2. Logado com sucesso! Acessando o Timesheet...');

  await page.goto('http://localhost:8080/index.php/apps/oab_timesheet/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);

  // 1. Screenshot Meu Timesheet
  console.log('3. Capturando Meu Timesheet...');
  await page.screenshot({ path: path.join(__dirname, '../screenshots/01-meu-timesheet.png') });

  // 2. Screenshot Modal de Novo Apontamento
  console.log('4. Abrindo Modal de Apontamento...');
  const newBtn = await page.$('.ts-btn-primary');
  if (newBtn) {
    await newBtn.click();
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(__dirname, '../screenshots/03-modal-apontamento.png') });
    // Fechar modal
    const closeBtn = await page.$('.ts-btn-close');
    if (closeBtn) await closeBtn.click();
    await page.waitForTimeout(500);
  }

  // 3. Screenshot Projetos (Deck)
  console.log('5. Capturando Projetos (Deck)...');
  const deckTab = await page.$('button.ts-nav-item:has-text("Projetos")');
  if (deckTab) {
    await deckTab.click();
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(__dirname, '../screenshots/02-projetos-deck.png') });
  }

  // 4. Screenshot Relatórios
  console.log('6. Capturando Relatórios...');
  const repTab = await page.$('button.ts-nav-item:has-text("Relatórios")');
  if (repTab) {
    await repTab.click();
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(__dirname, '../screenshots/04-relatorios.png') });
  }

  console.log('✅ Todas as capturas concluídas com sucesso!');
  await browser.close();
}

main().catch(err => {
  console.error('Erro na captura:', err);
  process.exit(1);
});
