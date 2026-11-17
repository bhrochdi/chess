// Test de fumée navigateur : un scénario HTTP complet dans Chromium (l'application a besoin du serveur).
// Toute erreur console, exception de page, dialogue ou réponse HTTP >= 400 fait échouer le test.
import { chromium } from 'playwright-core';

const BASE_URL = process.env.BASE_URL || 'http://web/';
const T = 20000;
const errors = [];
let scenario = '';
let step = '';

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const context = await browser.newContext();
let page = null;

function record(kind, msg) {
  errors.push({ scenario, step, kind, msg });
}

function collect(p, origin) {
  p.on('console', (m) => { if (m.type() === 'error') record('console.error', m.text()); });
  p.on('pageerror', (e) => record('pageerror', String(e.stack || e)));
  p.on('dialog', (d) => { record('dialog', d.message()); d.dismiss().catch(() => {}); });
  p.on('response', (r) => {
    if (r.status() >= 400 && new URL(r.url()).origin === origin) record('http ' + r.status(), r.url());
  });
}

async function newPage(origin) {
  const p = await context.newPage();
  collect(p, origin);
  await p.route('**/*', (route) => {
    const u = new URL(route.request().url());
    if (u.pathname === '/favicon.ico') return route.fulfill({ status: 204 });
    if (u.protocol === 'file:' || u.origin === origin) return route.continue();
    return route.fulfill({ status: 200, body: '' });
  });
  return p;
}

/* Attend que fn(arg), exécutée dans la page, soit vraie. Remplace page.waitForFunction, qui évalue sa
   condition comme une chaîne et que la politique de sécurité du site (CSP sans 'unsafe-eval') bloque ;
   page.evaluate n'y est pas soumis. Le test vérifie ainsi l'application avec sa vraie CSP. */
async function until(p, fn, arg, { timeout = T } = {}) {
  const deadline = Date.now() + timeout;
  for (;;) {
    try { if (await p.evaluate(fn, arg)) return; }
    catch (e) { if (!/context was destroyed|navigation/i.test(String(e.message))) throw e; }
    if (Date.now() > deadline) throw new Error('condition non remplie après ' + timeout + ' ms : ' + String(fn).slice(0, 120));
    await new Promise((r) => setTimeout(r, 100));
  }
}

const wait = (sel, opts = {}) => page.waitForSelector(sel, { timeout: T, ...opts });
const waitText = (sel, text) =>
  until(page, ([s, t]) => (document.querySelector(s)?.textContent || '').includes(t),
    [sel, text], { timeout: T });
const view = async (v) => {
  await page.click(`nav button[data-view=${v}]`);
  await wait(`#view-${v}.active`);
};
const S = (name) => { step = name; };

async function http() {
  scenario = 'http';
  const origin = new URL(BASE_URL).origin;
  page = await newPage(origin);

  S('chargement');
  const deadline = Date.now() + 60000;
  for (;;) {
    try { await page.goto(BASE_URL, { timeout: 10000 }); break; }
    catch (e) { if (Date.now() > deadline) throw e; await new Promise((r) => setTimeout(r, 2000)); }
  }
  await until(page, () => document.querySelectorAll('#localBoard .sq').length === 64, null, { timeout: T });

  S('inscription');
  await page.click('#btnAuth');
  await page.click('#tabRegister');
  await page.fill('#authEmail', 'fumee@example.test');
  await page.fill('#authPseudo', 'Fumee');
  await page.fill('#authPass', 'fumee-123');
  await page.click('#authSubmit');
  await wait('#navAdmin', { state: 'visible' });

  for (const v of ['home', 'local', 'online', 'salon', 'dm', 'tournois', 'admin']) {
    S('vue ' + v);
    await view(v);
  }

  S('partie locale contre l\'ordinateur');
  await view('local');
  await page.locator('#localBoard .sq').nth(52).click();
  await page.locator('#localBoard .sq').nth(36).click();
  await until(page,
    () => [...document.querySelectorAll('#localMoves .ply')].filter((s) => s.textContent.trim()).length >= 2,
    null, { timeout: T });

  S('annulation contre l\'ordinateur');
  await page.click('#btnLocalUndo');
  await until(page,
    () => [...document.querySelectorAll('#localMoves .ply')].filter((s) => s.textContent.trim()).length === 0,
    null, { timeout: T });

  S('partie en ligne');
  await view('home');
  await page.click('#btnCreateGame');
  await wait('#view-online.active');
  await waitText('#onlineStatus', 'attente');
  await page.fill('#gameChatInput', 'coucou partie');
  await page.click('#gameChatSend');
  await waitText('#gameChatLog', 'coucou partie');
  await page.click('#btnOnlineLeave');

  S('annulation du défi depuis l\'accueil');
  await page.locator('#openGames button', { hasText: 'Annuler' }).first().click();
  await page.click('#confirmYes');
  await until(page, () => !document.querySelector('#openGames button.danger'), null, { timeout: T });

  S('annulation du défi depuis la partie');
  await page.click('#btnCreateGame');
  await wait('#view-online.active');
  await wait('#btnCancelChallenge', { state: 'visible' });
  await page.click('#btnCancelChallenge');
  await page.click('#confirmYes');
  await wait('#view-home.active');
  await until(page, () => !document.querySelector('#openGames button.danger'), null, { timeout: T });

  S('salon');
  await view('salon');
  await page.fill('#salonInput', 'bonjour salon');
  await page.click('#salonSend');
  await waitText('#salonLog', 'bonjour salon');

  S('admin');
  await view('admin');
  await page.fill('#admRoomName', 'Analyses');
  await page.click('#admRoomCreate');
  await waitText('#admRooms', 'Analyses');
  await waitText('#admLog', 'Création du salon « Analyses »');
  await waitText('#admStats', 'Comptes');
  await page.fill('#admTournNom', 'Tournoi fumée');
  await page.click('#admTournCreate');
  await waitText('#admTournList', 'Tournoi fumée');

  S('tournois');
  await view('tournois');
  await waitText('#tournList', 'Tournoi fumée');
  await page.locator('#tournList button', { hasText: 'Voir' }).first().click();
  await page.locator('button', { hasText: "M'inscrire" }).first().click();
  await page.locator('button', { hasText: 'Me désinscrire' }).first().waitFor({ timeout: T });
  await page.waitForTimeout(7000);

  S('déconnexion');
  await view('home');
  await page.waitForTimeout(9000);
  await page.click('#btnLogout');
  await waitText('#profileLine', 'Connecte-toi');

  S('invité');
  await page.click('#btnAuth');
  await page.fill('#authPseudo', '');
  await page.click('#authGuest');
  await waitText('#profileLine', 'Invite_');
  await page.reload();
  await waitText('#profileLine', 'Invite_');
}


/* Deux joueurs, deux sessions : défi, coups dans les deux sens, nulle proposée puis acceptée. */
async function duel() {
  scenario = 'duel';
  const origin = new URL(BASE_URL).origin;
  const other = await browser.newContext();
  const pageA = await newPage(origin);
  const pageB = await other.newPage();
  collect(pageB, origin);
  const on = (p) => ({
    click: (sel) => p.click(sel),
    text: (sel, t) => until(p, ([s, x]) => (document.querySelector(s)?.textContent || '').includes(x), [sel, t], { timeout: T }),
    plies: (n) => until(p, (k) => [...document.querySelectorAll('#onlineMoves .ply')].filter((e) => e.textContent.trim()).length === k, n, { timeout: T }),
  });
  const register = async (p, name) => {
    await p.goto(BASE_URL);
    await until(p, () => document.querySelectorAll('#localBoard .sq').length === 64, null, { timeout: T });
    await p.click('#btnAuth');
    await p.click('#tabRegister');
    await p.fill('#authEmail', name.toLowerCase() + '@example.test');
    await p.fill('#authPseudo', name);
    await p.fill('#authPass', 'duel-1234');
    await p.click('#authSubmit');
    await on(p).text('#profileLine', name);
  };
  const A = on(pageA), B = on(pageB);
  page = pageA;

  S('inscriptions');
  await register(pageA, 'DuelBlanc');
  await register(pageB, 'DuelNoir');

  S('défi');
  await pageA.selectOption('#newColor', 'w');
  await A.click('#btnCreateGame');
  await A.text('#onlineStatus', 'attente');
  await B.text('#openGames', 'DuelBlanc');
  await pageB.locator('#openGames button', { hasText: 'Rejoindre' }).first().click();
  await B.text('#onlineStatus', 'Au trait');

  S('coups');
  await A.text('#onlineStatus', 'à toi');
  await pageA.locator('#onlineBoard .sq').nth(52).click();
  await pageA.locator('#onlineBoard .sq').nth(36).click();
  await B.plies(1);
  await B.text('#onlineStatus', 'à toi');
  await pageB.locator('#onlineBoard .sq').nth(51).click();   // échiquier retourné : e7 vu des Noirs
  await pageB.locator('#onlineBoard .sq').nth(35).click();   // e5
  await A.plies(2);

  S('nulle');
  await B.click('#btnDraw');
  await A.text('#onlineStatus', 'propose la nulle');
  await A.click('#acceptDraw');
  await A.text('#onlineStatus', 'Partie nulle');
  await B.text('#onlineStatus', 'Partie nulle');

  S('relecture');
  for (const p of [pageA, pageB]) { await p.waitForSelector('#endOverlay.show', { timeout: T }); await p.click('#endClose'); }
  const square = (p, i) => p.locator('#onlineBoard .sq').nth(i).textContent();
  await pageA.waitForSelector('#replayBar:not([hidden])', { timeout: T });
  await A.text('#replayPos', '2/2');
  await A.click('#replayFirst');
  await A.text('#replayPos', 'Position de départ');
  if ((await square(pageA, 52)) !== '♙' || (await square(pageA, 36)) !== '') throw new Error('position de départ attendue en relecture');
  await A.click('#replayNext');
  await A.text('#replayPos', '1/2');
  if ((await square(pageA, 36)) !== '♙') throw new Error('e4 attendu après le premier coup');
  await pageA.locator('#onlineMoves .ply.pick').nth(1).click();
  await A.text('#replayPos', '2/2');
  await pageA.keyboard.press('ArrowLeft');
  await A.text('#replayPos', '1/2');

  S('historique');
  await pageB.click('nav button[data-view=home]');
  await B.text('#historyList', 'DuelBlanc');
  await B.text('#historyList', 'Nulle');
  await pageB.locator('#historyList button', { hasText: 'Revoir' }).first().click();
  await pageB.waitForSelector('#replayBar:not([hidden])', { timeout: T });
  await B.text('#replayPos', '2/2');

  S('messages privés');
  await pageA.click('nav button[data-view=dm]');
  await pageA.fill('#dmNewPeer', 'DuelNoir');
  await A.click('#dmNewBtn');
  await A.text('#dmTitle', 'DuelNoir');
  await pageA.fill('#dmInput', 'bien joué');
  await A.click('#dmSend');
  await A.text('#dmLog', 'bien joué');
  await pageB.click('nav button[data-view=dm]');
  await B.text('#dmThreads', 'DuelBlanc');
  await pageB.locator('#dmThreads button', { hasText: 'DuelBlanc' }).click();
  await B.text('#dmLog', 'bien joué');

  S('tournoi : partie liée et résultat reporté');
  const third = await browser.newContext();
  const pageR = await third.newPage();
  collect(pageR, origin);
  const R = on(pageR);
  await pageR.goto(BASE_URL);
  await pageR.click('#btnAuth');
  await pageR.fill('#authPseudo', 'Fumee');
  await pageR.fill('#authPass', 'fumee-123');
  await pageR.click('#authSubmit');
  await R.text('#profileLine', 'ADMIN');
  await pageR.click('nav button[data-view=admin]');
  await pageR.fill('#admTournNom', 'Coupe duel');
  await pageR.click('#admTournCreate');
  await R.text('#admTournDetail', 'Coupe duel');
  for (const [p, X] of [[pageA, A], [pageB, B]]) {
    await p.click('nav button[data-view=tournois]');
    await X.text('#tournList', 'Coupe duel');
    await p.locator('#tournList .item', { hasText: 'Coupe duel' }).locator('button').click();
    await p.locator('button', { hasText: "M'inscrire" }).click();
    await p.locator('button', { hasText: 'Me désinscrire' }).waitFor({ timeout: T });
  }
  await pageR.locator('#admTournDetail button', { hasText: 'Lancer le tournoi' }).click();
  await R.text('#admTournDetail', 'En cours');
  await pageA.locator('button', { hasText: 'Jouer ma partie' }).waitFor({ timeout: T });
  await pageA.locator('button', { hasText: 'Jouer ma partie' }).click();
  await A.text('#onlineStatus', 'Au trait');
  await A.click('#btnResign');
  await A.click('#confirmYes');
  await A.text('#onlineStatus', 'Abandon');
  await pageR.click('nav button[data-view=home]');
  await pageR.click('nav button[data-view=admin]');
  // Ligne du tournoi visé : déjà sélectionné, son bouton affiche « ✓ Géré » et non « Gérer ».
  await pageR.locator('#admTournList tr', { hasText: 'Coupe duel' }).locator('button', { hasText: /Gér/ }).click();
  await R.text('#admTournDetail', '0-1');
  await third.close();
  await other.close();
}

for (const run of [http, duel]) {
  const before = errors.length;
  try { await run(); }
  catch (e) { record('exception', String(e.message || e)); }
  if (errors.length > before) {
    try { await page.screenshot({ path: '/out/echec.png' }); } catch {}
  }
  await page?.close().catch(() => {});
}
await browser.close();

if (errors.length) {
  for (const e of errors) console.error(`[${e.scenario} / ${e.step}] ${e.kind}: ${e.msg}`);
  process.exit(1);
}
console.log('fumée OK');
