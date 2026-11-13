'use strict';
/* ============================================================
   VUE TOURNOIS (utilisateur) — api/tournaments.php
============================================================ */
let tournSel = null;

async function refreshTournois() {
  $('tournLoginHint').style.display = currentUser ? 'none' : '';
  let list;
  try { list = (await api('tournaments', 'list')).tournaments; } catch (e) { return; }
  const box = $('tournList');
  box.innerHTML = '';
  for (const t of list) {
    const item = document.createElement('div'); item.className = 'item';
    item.innerHTML = '<span><strong>' + esc(t.name) + '</strong> <span class="small">· ' +
      statutLabelTourn(t.status) + ' · ' + t.playerCount + ' inscrits · ' + tcLabelTourn(t.tc) + '</span></span>';
    const b = document.createElement('button');
    b.textContent = tournSel === t.id ? '✓ Ouvert' : 'Voir';
    b.addEventListener('click', () => { tournSel = t.id; refreshTournois(); });
    item.appendChild(b);
    box.appendChild(item);
  }
  await renderTournDetail();
}

async function renderTournDetail() {
  const card = $('tournDetailCard'), box = $('tournDetail');
  if (!tournSel) { card.style.display = 'none'; box.innerHTML = ''; return; }
  let T;
  try { T = (await api('tournaments', 'get', { id: tournSel })).tournament; }
  catch (e) { tournSel = null; card.style.display = 'none'; return; }
  card.style.display = ''; box.innerHTML = '';
  box.appendChild(tournamentHeader(T));
  if (T.status === 'inscriptions') {
    const bar = document.createElement('div'); bar.className = 'toolbar'; bar.style.margin = '8px 0';
    if (!T.registrationOpen) {
      bar.innerHTML = '<span class="small">Les inscriptions sont fermées.</span>';
    } else {
      const b = document.createElement('button');
      b.className = T.registered ? 'danger' : 'primary';
      b.textContent = T.registered ? 'Me désinscrire' : "M'inscrire";
      b.addEventListener('click', () => tournamentRegistration(T.id, T.registered ? 'unregister' : 'register'));
      bar.appendChild(b);
    }
    box.appendChild(bar);
  }
  if (T.myPairing && T.myPairing.gameId) {
    const bar = document.createElement('div'); bar.style.margin = '8px 0';
    const b = document.createElement('button'); b.className = 'primary';
    b.textContent = '♟ Jouer ma partie (ronde ' + T.myPairing.round + ')';
    b.addEventListener('click', () => enterOnlineGame(T.myPairing.gameId));
    bar.appendChild(b);
    box.appendChild(bar);
  }
  renderTournamentBody(box, T, (round, p, row) => {
    if (!p.gameId) return;
    const b = document.createElement('button');
    b.textContent = p.result ? 'Revoir' : '👁 Suivre';
    b.addEventListener('click', () => enterOnlineGame(p.gameId));
    row.appendChild(b);
  });
}

async function tournamentRegistration(id, action) {
  if (!requirePseudo()) return;
  try { await api('tournaments', action, { id }); } catch (e) { alert(e.message); }
  refreshTournois();
}
setInterval(() => { if (currentView === 'tournois' && !document.hidden) refreshTournois(); }, 6000);
