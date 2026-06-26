'use strict';
/*
 * Tests unitaires — génération élimination directe et appariement suisse.
 * Exécution :  node tests/tournois-formats.test.js
 *
 * Fonctions dupliquées depuis index.html le temps du monofichier
 * (disparaîtra à refactor/decoupage). Garder identiques.
 */

function ordreBracket(size) {
  let a = [1];
  while (a.length < size) {
    const m = a.length * 2 + 1;
    const b = [];
    for (const x of a) { b.push(x); b.push(m - x); }
    a = b;
  }
  return a.map(x => x - 1);
}
function genererElimination(participants) {
  let size = 1; while (size < participants.length) size *= 2;
  const seeds = participants.slice();
  while (seeds.length < size) seeds.push(null);
  const ordre = ordreBracket(size);
  const matchs = [];
  for (let i = 0; i < size; i += 2) {
    const blanc = seeds[ordre[i]], noir = seeds[ordre[i + 1]];
    const m = { blanc, noir, gameId: null, resultat: null };
    if (blanc && !noir) m.resultat = '1-0';
    else if (noir && !blanc) m.resultat = '0-1';
    matchs.push(m);
  }
  return [{ numero: 1, matchs }];
}
function calculerClassement(T) {
  const stats = {};
  for (const p of T.participants) stats[p] = { pseudo: p, points: 0, joues: 0, victoires: 0 };
  for (const ronde of T.rondes) for (const m of ronde.matchs) {
    if (!m.resultat) continue;
    if (m.blanc && !m.noir) { if (stats[m.blanc]) { stats[m.blanc].points += 1; stats[m.blanc].joues++; stats[m.blanc].victoires++; } continue; }
    if (m.noir && !m.blanc) { if (stats[m.noir]) { stats[m.noir].points += 1; stats[m.noir].joues++; stats[m.noir].victoires++; } continue; }
    if (!stats[m.blanc] || !stats[m.noir]) continue;
    stats[m.blanc].joues++; stats[m.noir].joues++;
    if (m.resultat === '1-0') { stats[m.blanc].points += 1; stats[m.blanc].victoires++; }
    else if (m.resultat === '0-1') { stats[m.noir].points += 1; stats[m.noir].victoires++; }
    else { stats[m.blanc].points += 0.5; stats[m.noir].points += 0.5; }
  }
  return Object.values(stats).sort((a, b) =>
    b.points - a.points || b.victoires - a.victoires || a.pseudo.localeCompare(b.pseudo));
}
function aEuBye(T, pseudo) {
  for (const r of T.rondes) for (const m of r.matchs)
    if ((m.blanc === pseudo && !m.noir) || (m.noir === pseudo && !m.blanc)) return true;
  return false;
}
function apparierSuisse(T) {
  let joueurs = calculerClassement(T).map(c => c.pseudo);
  const dejaJoue = new Set();
  for (const r of T.rondes) for (const m of r.matchs)
    if (m.blanc && m.noir) { dejaJoue.add(m.blanc + '|' + m.noir); dejaJoue.add(m.noir + '|' + m.blanc); }
  let bye = null;
  if (joueurs.length % 2 === 1) {
    for (let i = joueurs.length - 1; i >= 0; i--) if (!aEuBye(T, joueurs[i])) { bye = joueurs[i]; break; }
    if (!bye) bye = joueurs[joueurs.length - 1];
    joueurs = joueurs.filter(p => p !== bye);
  }
  const matchs = [];
  const used = new Set();
  for (let i = 0; i < joueurs.length; i++) {
    if (used.has(joueurs[i])) continue;
    let adv = null;
    for (let j = i + 1; j < joueurs.length; j++)
      if (!used.has(joueurs[j]) && !dejaJoue.has(joueurs[i] + '|' + joueurs[j])) { adv = joueurs[j]; break; }
    if (!adv) for (let j = i + 1; j < joueurs.length; j++)
      if (!used.has(joueurs[j])) { adv = joueurs[j]; break; }
    if (!adv) break;
    used.add(joueurs[i]); used.add(adv);
    matchs.push({ blanc: joueurs[i], noir: adv, gameId: null, resultat: null });
  }
  if (bye) matchs.push({ blanc: bye, noir: null, gameId: null, resultat: '1-0' });
  const dernier = T.rondes.length ? T.rondes[T.rondes.length - 1].numero : 0;
  return { numero: dernier + 1, matchs };
}

/* -------- harnais -------- */
let echecs = 0;
function check(cond, titre) { console.log((cond ? '  ✓ ' : '  ✗ ') + titre); if (!cond) echecs++; }

/* ===== Élimination ===== */
console.log('Élimination — 8 joueurs (puissance de 2) :');
{
  const j = ['A','B','C','D','E','F','G','H'];
  const rondes = genererElimination(j);
  check(rondes[0].matchs.length === 4, '4 matchs en ronde 1');
  const joueursPlaces = rondes[0].matchs.flatMap(m => [m.blanc, m.noir]).filter(Boolean).sort();
  check(joueursPlaces.length === 8 && new Set(joueursPlaces).size === 8, 'les 8 joueurs placés une fois');
  check(rondes[0].matchs.every(m => !m.resultat), 'aucun bye (pas de résultat pré-rempli)');
  // tête de série 1 (A) contre la plus basse (H) ? ordreBracket(8) standard
  check(rondes[0].matchs[0].blanc === 'A', 'la 1re tête de série ouvre le bracket');
}
console.log('Élimination — 5 joueurs (byes) :');
{
  const j = ['A','B','C','D','E'];
  const rondes = genererElimination(j);
  check(rondes[0].matchs.length === 4, '4 matchs (bracket de 8)');
  const byes = rondes[0].matchs.filter(m => m.resultat).length;
  check(byes === 3, '3 byes (8 - 5 = 3 places vides)');
  const survivants = rondes[0].matchs.map(m => m.resultat === '1-0' ? m.blanc : m.resultat === '0-1' ? m.noir : null).filter(Boolean);
  check(survivants.every(s => j.includes(s)), 'les byes profitent à des joueurs réels');
}

/* ===== Suisse ===== */
console.log('Suisse — ronde 1, 6 joueurs :');
{
  const T = { participants: ['A','B','C','D','E','F'], rondes: [] };
  const r1 = apparierSuisse(T);
  check(r1.matchs.length === 3, '3 matchs');
  const places = r1.matchs.flatMap(m => [m.blanc, m.noir]);
  check(new Set(places).size === 6, '6 joueurs distincts appariés');
}
console.log('Suisse — pas de rematch en ronde 2 :');
{
  const T = { participants: ['A','B','C','D'], rondes: [
    { numero: 1, matchs: [
      { blanc: 'A', noir: 'B', gameId: null, resultat: '1-0' },
      { blanc: 'C', noir: 'D', gameId: null, resultat: '1-0' }
    ] }
  ] };
  const r2 = apparierSuisse(T);
  const paires = r2.matchs.map(m => [m.blanc, m.noir].sort().join('|'));
  check(!paires.includes('A|B') && !paires.includes('C|D'), 'aucune paire de la ronde 1 rejouée');
  // leaders A & C (1 pt) doivent être appariés ensemble
  check(paires.includes('A|C') || paires.includes('C|A'), 'les deux leaders (A, C) sont appariés');
}
console.log('Suisse — bye si impair :');
{
  const T = { participants: ['A','B','C','D','E'], rondes: [] };
  const r1 = apparierSuisse(T);
  const bye = r1.matchs.find(m => !m.noir || !m.blanc);
  check(!!bye && bye.resultat === '1-0', 'un bye attribué (1 pt)');
  const places = r1.matchs.flatMap(m => [m.blanc, m.noir]).filter(Boolean);
  check(new Set(places).size === 5, 'les 5 joueurs présents (4 appariés + 1 bye)');
}

if (echecs === 0) { console.log('\nTOUS LES TESTS PASSENT.'); process.exit(0); }
else { console.log('\n' + echecs + ' test(s) en ECHEC.'); process.exit(1); }
