'use strict';
/*
 * Test unitaire — génération de l'appariement round-robin (algorithme du cercle).
 * Exécution :  node tests/round-robin.test.js
 *
 * NB : la fonction est dupliquée ici car le code vit encore dans le monofichier
 * index.html. Cette duplication disparaîtra à l'étape « refactor/decoupage »
 * (extraction du JS). Garder les deux versions identiques en attendant.
 */

function genererRoundRobin(participants) {
  const arr = participants.slice();
  if (arr.length % 2 === 1) arr.push(null);
  const n = arr.length;
  const rondes = [];
  for (let r = 0; r < n - 1; r++) {
    const matchs = [];
    for (let i = 0; i < n / 2; i++) {
      const a = arr[i], b = arr[n - 1 - i];
      if (a === null || b === null) continue;
      const blanc = (r % 2 === 0) ? a : b;
      const noir  = (r % 2 === 0) ? b : a;
      matchs.push({ blanc, noir, gameId: null, resultat: null });
    }
    rondes.push({ numero: r + 1, matchs });
    arr.splice(1, 0, arr.pop());
  }
  return rondes;
}

/* -------- mini-harnais d'assertions -------- */
let echecs = 0;
function check(cond, titre) {
  if (cond) { console.log('  ✓ ' + titre); }
  else { console.log('  ✗ ' + titre); echecs++; }
}

/* Vérifie qu'un appariement est un round-robin complet et valide. */
function verifierRoundRobin(joueurs) {
  const rondes = genererRoundRobin(joueurs);
  const n = joueurs.length;
  const rondesAttendues = (n % 2 === 0) ? n - 1 : n;
  check(rondes.length === rondesAttendues,
    n + ' joueurs -> ' + rondesAttendues + ' rondes (obtenu ' + rondes.length + ')');

  // Chaque paire non ordonnée doit apparaître exactement une fois.
  const paires = {};
  let okUnMatchParRondeParJoueur = true;
  for (const ronde of rondes) {
    const vus = new Set();
    for (const m of ronde.matchs) {
      if (vus.has(m.blanc) || vus.has(m.noir)) okUnMatchParRondeParJoueur = false;
      vus.add(m.blanc); vus.add(m.noir);
      const cle = [m.blanc, m.noir].sort().join('|');
      paires[cle] = (paires[cle] || 0) + 1;
    }
  }
  check(okUnMatchParRondeParJoueur, '  un seul match par joueur et par ronde');

  let toutesUnique = true, total = 0;
  for (const c in paires) { total++; if (paires[c] !== 1) toutesUnique = false; }
  const attendu = n * (n - 1) / 2;
  check(toutesUnique, '  chaque paire se rencontre au plus une fois');
  check(total === attendu,
    '  ' + attendu + ' rencontres distinctes (obtenu ' + total + ')');

  // Couleurs valides
  let couleursOk = true;
  for (const ronde of rondes) for (const m of ronde.matchs)
    if (!joueurs.includes(m.blanc) || !joueurs.includes(m.noir) || m.blanc === m.noir) couleursOk = false;
  check(couleursOk, '  couleurs assignées à des joueurs réels et distincts');
}

console.log('Round-robin — 4 joueurs (pair) :');
verifierRoundRobin(['Alice', 'Bob', 'Chloé', 'David']);
console.log('Round-robin — 5 joueurs (impair, avec exempt) :');
verifierRoundRobin(['Alice', 'Bob', 'Chloé', 'David', 'Eve']);

if (echecs === 0) { console.log('\nTOUS LES TESTS PASSENT.'); process.exit(0); }
else { console.log('\n' + echecs + ' test(s) en ECHEC.'); process.exit(1); }
