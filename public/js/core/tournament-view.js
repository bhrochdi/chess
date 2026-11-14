'use strict';
/* ===================== Affichage d'un tournoi reçu du serveur =====================
   Partagé par la vue Tournois et l'administration. */
function statutLabelTourn(s) {
  return s === 'inscriptions' ? 'Inscriptions ouvertes' : s === 'en-cours' ? 'En cours' : 'Terminé';
}
function tcLabelTourn(tc) {
  if (!tc) return 'sans pendule';
  return Math.round(tc.base / 60) + ' min' + (tc.inc ? ' + ' + tc.inc + ' s' : '');
}
function formatLabelTourn(f) {
  return f === 'elimination' ? 'Élimination directe' : f === 'suisse' ? 'Système suisse' : 'Round-robin';
}
function progressionRonde(T) {
  if (T.status !== 'en-cours') return '';
  if (T.format === 'round-robin') return ' · ronde ' + T.currentRound + '/' + T.rounds.length;
  if (T.format === 'suisse') return ' · ronde ' + T.currentRound + '/' + T.plannedRounds;
  return ' · ronde ' + T.currentRound;
}

function tournamentHeader(T) {
  const head = document.createElement('div');
  head.innerHTML = '<h2 style="color:var(--gold)">' + esc(T.name) + ' — ' + statutLabelTourn(T.status) +
    ' <span class="small">(' + formatLabelTourn(T.format) + ', ' + tcLabelTourn(T.tc) + progressionRonde(T) + ')</span></h2>' +
    (T.description ? '<p class="small">' + esc(T.description) + '</p>' : '');
  return head;
}

/* Inscrits, rondes et classement. pairingActions(round, pairing, row) ajoute les boutons d'une rencontre. */
function renderTournamentBody(box, T, pairingActions) {
  const meId = currentUser ? currentUser.id : null;
  const pdiv = document.createElement('div');
  pdiv.innerHTML = '<p class="small" style="margin:6px 0"><strong>Inscrits (' + T.players.length + ')</strong> : ' +
    (T.players.length ? T.players.map(p => esc(p.pseudo)).join(', ') : '—') + '</p>';
  box.appendChild(pdiv);
  for (const round of T.rounds) {
    const rdiv = document.createElement('div'); rdiv.style.margin = '10px 0';
    const titre = document.createElement('p'); titre.className = 'small';
    titre.innerHTML = '<strong>Ronde ' + round.number + (round.number === T.currentRound ? ' — courante' : '') + '</strong>';
    rdiv.appendChild(titre);
    for (const p of round.pairings) {
      const row = document.createElement('div'); row.className = 'item';
      if (meId && ((p.white && p.white.id === meId) || (p.black && p.black.id === meId))) row.style.borderColor = 'var(--gold-deep)';
      const name = s => s ? esc(s.pseudo) : '<em>exempt</em>';
      row.innerHTML = '<span class="small">' + name(p.white) + ' <span style="color:var(--muted)">vs</span> ' +
        name(p.black) + ' &nbsp; <strong>' + (p.result || '—') + '</strong></span>';
      pairingActions(round, p, row);
      rdiv.appendChild(row);
    }
    box.appendChild(rdiv);
  }
  if (T.standings.length) {
    const ctab = document.createElement('div');
    let html = '<p class="small" style="margin:8px 0 4px"><strong>Classement</strong></p>' +
      '<table class="admin-table"><thead><tr><th>#</th><th>Joueur</th><th>Pts</th><th>J</th><th>V</th></tr></thead><tbody>';
    T.standings.forEach((c, i) => {
      const moi = meId && c.player.id === meId;
      html += '<tr' + (moi ? ' style="color:var(--gold-bright)"' : '') + '><td class="small">' + (i + 1) + '</td><td>' +
        esc(c.player.pseudo) + '</td><td class="small">' + c.points + '</td><td class="small">' + c.played +
        '</td><td class="small">' + c.wins + '</td></tr>';
    });
    ctab.innerHTML = html + '</tbody></table>';
    box.appendChild(ctab);
  }
}
