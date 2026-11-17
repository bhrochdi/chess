'use strict';

async function refreshLobby() {
  // Joueurs
  const players = await onlinePlayers();
  const pl = $('playersList');
  pl.innerHTML = '';
  for (const p of players) {
    const item = document.createElement('div');
    item.className = 'item';
    item.innerHTML = '<span><span class="dot"></span>' + esc(p) + (p === pseudo ? ' <span class="small">(moi)</span>' : '') + '</span>';
    if (p !== pseudo && pseudo) {
      const b = document.createElement('button');
      b.textContent = '✉️';
      b.title = 'Message privé';
      b.addEventListener('click', () => { openThread(p); switchView('dm'); });
      item.appendChild(b);
    }
    pl.appendChild(item);
  }
  // Parties en ligne : ouvertes, en cours (le serveur filtre et trie)
  let games = [];
  try { games = (await api('games', 'list')).games; } catch (e) { return; }
  const open = $('openGames'), live = $('liveGames');
  open.innerHTML = ''; live.innerHTML = '';
  const meId = currentUser ? currentUser.id : null;
  for (const g of games) {
    const tcTxt = g.tc ? (g.tc.base/60) + ' min' + (g.tc.inc ? ' + ' + g.tc.inc + ' s' : '') : 'sans pendule';
    const mine = meId && ((g.white && g.white.id === meId) || (g.black && g.black.id === meId));
    if (g.status === 'open') {
      const item = document.createElement('div');
      item.className = 'item';
      const creator = (g.white || g.black).pseudo;
      item.innerHTML = '<span>♟ <strong>' + esc(creator) + '</strong> <span class="small">— ' + tcTxt + '</span></span>';
      const b = document.createElement('button');
      if (mine) {
        b.textContent = 'Reprendre';
        b.addEventListener('click', () => enterOnlineGame(g.id));
      } else {
        b.className = 'primary';
        b.textContent = 'Rejoindre';
        b.addEventListener('click', () => joinGame(g.id));
      }
      if (mine) {
        const actions = document.createElement('span');
        actions.className = 'actions';
        const cancel = document.createElement('button');
        cancel.className = 'danger';
        cancel.textContent = '✕ Annuler';
        cancel.title = 'Retirer ce défi';
        cancel.addEventListener('click', async () => { await cancelChallenge(g.id); refreshLobby(); });
        actions.append(b, cancel);
        item.appendChild(actions);
      } else {
        item.appendChild(b);
      }
      open.appendChild(item);
    } else if (g.status === 'playing') {
      const item = document.createElement('div');
      item.className = 'item';
      item.innerHTML = '<span>⚔ <strong>' + esc(g.white.pseudo) + '</strong> vs <strong>' + esc(g.black.pseudo) + '</strong> <span class="small">— ' + tcTxt + '</span></span>';
      const b = document.createElement('button');
      b.textContent = mine ? 'Reprendre' : '👁 Regarder';
      b.addEventListener('click', () => enterOnlineGame(g.id));
      item.appendChild(b);
      live.appendChild(item);
    }
  }
}
setInterval(() => { if (currentView === 'home' && !document.hidden) refreshLobby(); }, 5000);
/* -------- Historique : parties en ligne terminées du joueur, par pages de 20 -------- */
let historyOffset = 0;

async function refreshHistory(more) {
  const card = $('historyCard');
  if (!currentUser) { card.hidden = true; return; }
  const offset = more ? historyOffset : 0;
  let data;
  try { data = await api('games', 'history', { offset }); } catch (e) { return; }
  card.hidden = false;
  const box = $('historyList');
  if (!more) box.innerHTML = '';
  for (const g of data.games) box.appendChild(historyItem(g));
  historyOffset = offset + data.games.length;
  $('historyMore').hidden = !data.more;
}

function historyItem(g) {
  const meId = currentUser ? currentUser.id : null;
  const o = historyOutcome(g, meId);
  const opponent = g.white && g.white.id === meId ? g.black : g.white;
  const color = g.white && g.white.id === meId ? 'Blancs' : 'Noirs';
  const day = new Date(g.updatedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
  const item = document.createElement('div');
  item.className = 'item';
  item.innerHTML = '<span><span class="outcome ' + o.tone + '">' + o.label + '</span> contre <strong>' +
    esc(opponent ? opponent.pseudo : '?') + '</strong> <span class="small">— ' + color + ', ' + esc(o.reason) +
    (o.reason ? ', ' : '') + esc(g.result || '') + ' · ' + esc(day) + '</span></span>';
  const b = document.createElement('button');
  b.textContent = '↺ Revoir';
  b.addEventListener('click', () => enterOnlineGame(g.id));
  item.appendChild(b);
  return item;
}

$('historyMore').addEventListener('click', () => refreshHistory(true));

/* -------- Création / rejoindre -------- */
$('btnCreateGame').addEventListener('click', async () => {
  if (!requirePseudo()) return;
  const tcv = $('newTc').value;
  const tc = tcv === 'inf' ? null : { base: +tcv.split('|')[0], inc: +tcv.split('|')[1] };
  try {
    const { game } = await api('games', 'create', { mode: 'online', tc, color: $('newColor').value });
    enterOnlineGame(game.id);
  } catch (e) { alert(e.message); }
});
async function joinGame(id) {
  if (!requirePseudo()) return;
  try {
    await api('games', 'join', { id });
    enterOnlineGame(id);
  } catch (e) { alert(e.message); refreshLobby(); }
}
