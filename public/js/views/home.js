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
      item.appendChild(b);
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
