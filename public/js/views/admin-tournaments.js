'use strict';
/* -------- Tournois (administration) — api/tournaments.php, journal tenu par le serveur -------- */
let admTournSel = null;

async function renderAdminTournois() {
  let list;
  try { list = (await api('tournaments', 'list')).tournaments; } catch (e) { return; }
  const tb = $('admTournList');
  tb.innerHTML = '';
  for (const t of list) {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td><strong>' + esc(t.name) + '</strong></td>' +
      '<td class="small">' + formatLabelTourn(t.format) + '</td>' +
      '<td class="small">' + statutLabelTourn(t.status) + '</td>' +
      '<td class="small">' + t.playerCount + '</td>';
    const td = document.createElement('td'); td.className = 'actions';
    td.appendChild(adminBtn(admTournSel === t.id ? '✓ Géré' : 'Gérer', () => { admTournSel = t.id; renderAdminTournois(); }));
    td.appendChild(adminBtn('Supprimer', () => supprimerTournoi(t), 'danger'));
    tr.appendChild(td); tb.appendChild(tr);
  }
  await renderAdminTournDetail();
}

/* Action d'administration ; le message d'erreur du serveur est montré tel quel. */
async function tournamentAdmin(action, payload) {
  try { await api('tournaments', action, payload); } catch (e) { alert(e.message); }
  renderAdminTournois();
}

async function renderAdminTournDetail() {
  const box = $('admTournDetail');
  box.innerHTML = '';
  if (!admTournSel) return;
  let T;
  try { T = (await api('tournaments', 'get', { id: admTournSel })).tournament; }
  catch (e) { admTournSel = null; return; }
  box.appendChild(tournamentHeader(T));
  const bar = document.createElement('div'); bar.className = 'toolbar'; bar.style.margin = '8px 0';
  if (T.status === 'inscriptions') {
    bar.appendChild(adminBtn(T.registrationOpen ? 'Fermer les inscriptions' : 'Ouvrir les inscriptions',
      () => tournamentAdmin('setRegistration', { id: T.id, open: !T.registrationOpen })));
    bar.appendChild(adminBtn('Lancer le tournoi', () => tournamentAdmin('start', { id: T.id }), 'primary'));
  } else if (T.status === 'en-cours') {
    if (T.canAdvance) bar.appendChild(adminBtn('Ronde suivante', () => rondeSuivante(T)));
    bar.appendChild(adminBtn('Clore le tournoi', () => cloreTournoi(T), 'danger'));
  }
  box.appendChild(bar);
  renderTournamentBody(box, T, (round, p, row) => {
    if (T.status !== 'en-cours' || round.number !== T.currentRound || !p.white || !p.black) return;
    const ctl = document.createElement('span'); ctl.className = 'actions';
    [['1-0', '1-0'], ['½', '1/2-1/2'], ['0-1', '0-1'], ['✕', null]].forEach(([lbl, val]) =>
      ctl.appendChild(adminBtn(lbl, () => tournamentAdmin('setResult', { id: T.id, round: round.number, board: p.board, result: val }))));
    row.appendChild(ctl);
  });
}

$('admTournCreate').addEventListener('click', async () => {
  const msg = $('admTournMsg');
  const tcv = $('admTournTc').value;
  const format = $('admTournFormat').value;
  try {
    const { tournament } = await api('tournaments', 'create', {
      name: $('admTournNom').value.trim(),
      description: $('admTournDesc').value.trim(),
      format,
      tc: tcv === 'inf' ? null : { base: +tcv.split('|')[0], inc: +tcv.split('|')[1] },
      rounds: format === 'suisse' ? (parseInt($('admTournRondes').value, 10) || 5) : null
    });
    $('admTournNom').value = ''; $('admTournDesc').value = ''; msg.textContent = '';
    admTournSel = tournament.id;
  } catch (e) {
    msg.textContent = e.message;
  }
  renderAdminTournois();
});
/* Le champ « nombre de rondes » ne concerne que le système suisse. */
$('admTournFormat').addEventListener('change', e => {
  $('admTournRondes').style.display = e.target.value === 'suisse' ? '' : 'none';
});

/* Toutes rondes : une ronde incomplète peut être passée après confirmation. */
async function rondeSuivante(T) {
  const current = T.rounds.find(r => r.number === T.currentRound);
  const incomplete = !!current && current.pairings.some(p => p.white && p.black && !p.result);
  let force = false;
  if (incomplete && T.format === 'round-robin') {
    force = await askConfirm('Ronde incomplète', 'Des résultats manquent à la ronde ' + T.currentRound + '. Passer quand même à la suivante ?');
    if (!force) return;
  }
  tournamentAdmin('nextRound', { id: T.id, force });
}
async function cloreTournoi(T) {
  if (await askConfirm('Clore le tournoi ?', 'Le classement final sera figé.')) tournamentAdmin('close', { id: T.id });
}
async function supprimerTournoi(t) {
  const ok = await askConfirm('Supprimer le tournoi ?', 'Le tournoi « ' + t.name + ' » sera supprimé.');
  if (!ok) return;
  if (admTournSel === t.id) admTournSel = null;
  tournamentAdmin('delete', { id: t.id });
}
