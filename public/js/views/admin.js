'use strict';
/* ============================================================
   ESPACE ADMINISTRATION — tout est contrôlé et journalisé par le serveur
   (api/admin.php, api/auth.php, api/games.php, api/rooms.php, api/tournaments.php).
============================================================ */
let adminClaimRefused = false;   // le serveur a répondu qu'un administrateur existe déjà

function isAdmin() { return !!(currentUser && currentUser.role === 'admin'); }

function statCard(n, label) {
  return '<div class="stat-card"><div class="n">' + n + '</div><div class="l">' + label + '</div></div>';
}

function adminBtn(label, fn, cls) {
  const b = document.createElement('button');
  b.textContent = label;
  if (cls) b.className = cls;
  b.addEventListener('click', fn);
  return b;
}

async function refreshAdmin() {
  const registered = !!currentUser && currentUser.provider !== 'guest';
  const canClaim = registered && !isAdmin() && !adminClaimRefused;
  $('admClaimBox').style.display = canClaim ? '' : 'none';
  $('adminPanels').style.display = isAdmin() ? '' : 'none';
  $('adminDenied').style.display = isAdmin() || canClaim ? 'none' : '';
  if (!isAdmin()) return;
  try {
    const [dash, users, games, rooms, journal] = await Promise.all([
      api('admin', 'dashboard'), api('auth', 'list'), api('games', 'adminList'), api('rooms', 'listAll'), api('admin', 'journal')
    ]);
    const c = dash.counts;
    $('admStats').innerHTML =
      statCard(c.accounts, 'Comptes') + statCard(c.online, 'En ligne') + statCard(c.open, 'Défis ouverts') +
      statCard(c.playing, 'En cours') + statCard(c.finished, 'Terminées') + statCard(c.roomMessages, 'Msgs salons');
    renderAdminUsers(users.users);
    renderAdminGames(games.games);
    renderAdminRooms(rooms.rooms);
    populateModRoomSelector(rooms.rooms);
    renderAdminLog(journal.entries);
  } catch (e) {
    alert(e.message);
    return;
  }
  renderAdminSalon();
  renderAdminTournois();
}

/* -------- Utilisateurs -------- */
function renderAdminUsers(users) {
  const tb = $('admUsers');
  tb.innerHTML = '';
  for (const u of users) {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td><strong>' + esc(u.pseudo) + '</strong>' +
      (u.role === 'admin' ? ' <span class="role-badge">ADMIN</span>' : '') +
      (u.banned ? ' <span class="ban-badge">SUSPENDU</span>' : '') + '</td>' +
      '<td class="small">' + esc(u.email || '—') + '</td>' +
      '<td class="small">' + esc(u.provider) + '</td>' +
      '<td class="small">' + u.stats.w + '/' + u.stats.l + '/' + u.stats.d + '</td>' +
      '<td class="small">' + new Date(u.createdAt).toLocaleDateString('fr-FR') + '</td>';
    const td = document.createElement('td');
    td.className = 'actions';
    td.appendChild(adminBtn(u.banned ? 'Réactiver' : 'Suspendre', () => adminApply(u.id, u.banned ? 'unban' : 'ban'), u.banned ? '' : 'danger'));
    if (u.role === 'admin') td.appendChild(adminBtn('Rétrograder', () => adminApply(u.id, 'demote')));
    else if (u.provider !== 'guest') td.appendChild(adminBtn('Promouvoir', () => adminApply(u.id, 'promote')));
    td.appendChild(adminBtn('Supprimer', () => adminDeleteUser(u), 'danger'));
    tr.appendChild(td);
    tb.appendChild(tr);
  }
}

async function adminApply(targetId, op) {
  try { await api('auth', 'adminUpdate', { targetId, op }); }
  catch (e) { alert(e.message); return; }
  if (currentUser && targetId === currentUser.id) {     // mon propre rôle a pu changer
    try { const r = await api('auth', 'me'); if (r.user) currentUser = r.user; } catch (e) { /* inchangé */ }
    refreshProfileUI();
  }
  refreshAdmin();
}

async function adminDeleteUser(u) {
  const ok = await askConfirm('Supprimer ' + u.pseudo + ' ?', 'Le compte sera supprimé définitivement.');
  if (ok) adminApply(u.id, 'delete');
}

/* -------- Parties -------- */
function renderAdminGames(games) {
  const box = $('admGames');
  box.innerHTML = '';
  const modes = { local: 'locale', ai: 'ordinateur', online: 'en ligne' };
  for (const g of games) {
    const item = document.createElement('div');
    item.className = 'item';
    const lbl = { open: 'ouverte', playing: 'en cours', finished: 'terminée' }[g.status] || g.status;
    const who = s => s ? esc(s.pseudo) : (g.mode === 'ai' ? 'ordinateur' : '?');
    item.innerHTML = '<span class="small"><strong>' + who(g.white) + '</strong> vs <strong>' + who(g.black) +
      '</strong> — ' + modes[g.mode] + ', ' + lbl + (g.result ? ' · ' + g.result : '') + '</span>';
    const acts = document.createElement('span');
    acts.style.display = 'flex'; acts.style.gap = '5px';
    if (g.mode === 'online') acts.appendChild(adminBtn('Voir', () => enterOnlineGame(g.id)));
    if (g.status !== 'finished') acts.appendChild(adminBtn('Clore', () => adminCloseGame(g)));
    acts.appendChild(adminBtn('Suppr.', () => adminDeleteGame(g), 'danger'));
    item.appendChild(acts);
    box.appendChild(item);
  }
}

async function gameAdmin(action, payload) {
  try { await api('games', action, payload); } catch (e) { alert(e.message); }
  refreshAdmin();
}

async function adminCloseGame(g) {
  const ok = await askConfirm('Clore la partie ?', 'Elle sera marquée terminée (décision administrative, nulle par défaut).');
  if (ok) gameAdmin('adminClose', { id: g.id });
}

async function adminDeleteGame(g) {
  const ok = await askConfirm('Supprimer la partie ?', 'Ses coups et ses commentaires seront effacés.');
  if (ok) gameAdmin('adminDelete', { id: g.id });
}

/* -------- Journal -------- */
function renderAdminLog(entries) {
  const box = $('admLog');
  box.innerHTML = '';
  for (const e of entries) {
    const d = document.createElement('div');
    d.className = 'item';
    d.innerHTML = '<span class="small"><strong>' + esc(e.actor.pseudo) + '</strong> · ' +
      new Date(e.at).toLocaleString('fr-FR') + ' — ' + esc(e.action) + '</span>';
    box.appendChild(d);
  }
}

/* -------- Maintenance -------- */
$('admPurge').addEventListener('click', async () => {
  const ok = await askConfirm('Purger les parties terminées ?', 'Leurs coups et leurs commentaires seront supprimés.');
  if (ok) gameAdmin('purge', {});
});

$('admPurgeGuests').addEventListener('click', async () => {
  const ok = await askConfirm('Purger les invités inactifs ?', 'Les comptes invités sans activité depuis 30 jours seront supprimés ; leurs parties restent.');
  if (!ok) return;
  try { alert((await api('auth', 'purgeGuests')).deleted + ' compte(s) invité(s) supprimé(s).'); } catch (e) { alert(e.message); }
  refreshAdmin();
});

/* -------- Revendication du rôle admin tant qu'aucun administrateur n'existe -------- */
$('adminClaim').addEventListener('click', async () => {
  try {
    currentUser = (await api('auth', 'claimAdmin')).user;
  } catch (e) {
    if (e.status === 409) adminClaimRefused = true;
    alert(e.message);
  }
  refreshProfileUI();
  refreshAdmin();
});
