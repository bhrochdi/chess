'use strict';
/* ============================================================
   PARTIE EN LIGNE — vue, synchro, commentaires (api/games.php, api/game-comments.php)
============================================================ */
let OR = null;              // partie reçue du serveur
let orReceivedAt = 0;
let onlineId = null;
let onlinePoll = null;
let onlineBusy = false;
let orFlagAsked = false;
let orViewer = null;        // compte pour lequel OR a été reçu (yourColor et les coups légaux en dépendent)
let lastChatId = null;      // dernier commentaire affiché : null avant la première réponse, 0 pour un fil vide

const viewerId = () => (currentUser ? currentUser.id : null);

const isMyTurn = () => !!OR && OR.status === 'playing' && OR.legalMoves.length > 0;
const playerNames = () => ({ w: OR.white ? OR.white.pseudo : '— attente —', b: OR.black ? OR.black.pseudo : '— attente —' });

const onlineBoard = ServerBoard($('onlineBoard'), {
  getGame: () => OR,
  flipped: () => !!OR && OR.yourColor === 'b',
  canMove: () => !onlineBusy && isMyTurn(),
  onMove: uci => onlineRequest('move', { uci, ply: OR.moves.length })
});

/* Garde l'état le plus récent : une réponse en retard ne fait pas reculer l'échiquier. */
function adoptOnline(game, force) {
  if (!force && OR && OR.id === game.id && !newer(game, OR) && game.drawOffer === OR.drawOffer && orViewer === viewerId()) return;
  const before = OR && OR.id === game.id ? OR : null;
  OR = game;
  orViewer = viewerId();
  orReceivedAt = Date.now();
  orFlagAsked = false;
  onlineBoard.clearSel();
  renderOnline();
  if (before && game.moves.length > before.moves.length) tinySound();
  if (before && before.status !== 'finished' && game.status === 'finished') showServerEnd(game);
}

async function onlineRequest(action, payload = {}) {
  if (!OR || onlineBusy) return;
  onlineBusy = true;
  try {
    adoptOnline((await api('games', action, Object.assign({ id: OR.id }, payload))).game, true);
  } catch (e) {
    if (e.status === 409) { try { adoptOnline((await api('games', 'get', { id: OR.id })).game, true); } catch (_) { /* reste affichée */ } }
    if (e.status !== 409 || !/avancé/.test(e.message)) alert(e.message);
  } finally {
    onlineBusy = false;
    renderOnline();
  }
}

function enterOnlineGame(id) {
  onlineId = id;
  OR = null;
  lastChatId = null;
  $('gameChatLog').innerHTML = '';
  switchView('online');
  $('onlineEmpty').style.display = 'none';
  $('onlineGameUI').style.display = '';
  syncOnline();
  if (onlinePoll) clearInterval(onlinePoll);
  onlinePoll = setInterval(() => { if (!document.hidden) syncOnline(); }, 2500);
}

$('btnOnlineLeave').addEventListener('click', () => {
  if (onlinePoll) clearInterval(onlinePoll);
  onlinePoll = null; OR = null; onlineId = null;
  $('onlineGameUI').style.display = 'none';
  $('onlineEmpty').style.display = '';
  switchView('home');
});

/* Relecture périodique : la version affichée est renvoyée au serveur, qui répond « unchanged » si rien
   n'a bougé. Elle ne vaut que pour la même partie et le même compte. */
async function syncOnline() {
  if (!onlineId) return;
  const known = OR && OR.id === onlineId && orViewer === viewerId() ? OR.version : null;
  try {
    const res = await api('games', 'get', known ? { id: onlineId, known } : { id: onlineId });
    if (!res.unchanged) adoptOnline(res.game);
  } catch (e) { /* prochain passage */ }
  await syncGameChat();
}

function renderOnline() {
  if (!OR) return;
  onlineBoard.render();
  renderServerSide($('view-online'), OR);
  renderServerMoves($('onlineMoves'), OR);
  renderServerClocks($('onlineClocks'), OR, playerNames(), orReceivedAt);
  const names = playerNames();
  const tcTxt = OR.tc ? (OR.tc.base / 60) + ' min' + (OR.tc.inc ? ' + ' + OR.tc.inc + ' s' : '') : 'sans pendule';
  $('onlineTitle').textContent = names.w + ' vs ' + names.b + ' — ' + tcTxt;
  const st = $('onlineStatus');
  const isPlayer = OR.yourColor === 'w' || OR.yourColor === 'b';
  if (OR.status === 'open') {
    st.innerHTML = '⏳ En attente d’un adversaire… <span class="small">Partage le défi via le salon !</span>';
  } else if (OR.status === 'finished') {
    const t = endText(OR);
    st.innerHTML = '<strong>' + esc(t.title) + '.</strong> ' + esc(t.detail) + ' (' + esc(OR.result || '') + ')';
  } else {
    const turn = parseFen(OR.fen).turn;
    const colDot = turn === 'w' ? '#faf6ec' : '#1c1611';
    const chk = OR.inCheck ? ' — <strong style="color:var(--check)">Échec !</strong>' : '';
    st.innerHTML = '<span class="turn-dot" style="background:' + colDot + '"></span>Au trait : <strong>' + esc(names[turn]) + '</strong>' +
      (OR.yourColor === turn ? ' (à toi !)' : '') + chk + (isPlayer ? '' : ' <span class="small">· mode spectateur</span>');
    if (OR.drawOffer && isPlayer && OR.drawOffer !== OR.yourColor) {
      st.innerHTML += '<div style="margin-top:8px"><span class="small">' + esc(names[OR.drawOffer]) +
        ' propose la nulle :</span> <button id="acceptDraw" class="primary" style="padding:4px 10px; font-size:13px">Accepter ½-½</button></div>';
      $('acceptDraw').addEventListener('click', () => onlineRequest('acceptDraw'));
    } else if (OR.drawOffer && OR.drawOffer === OR.yourColor) {
      st.innerHTML += '<div class="small" style="margin-top:6px">Proposition de nulle envoyée…</div>';
    }
  }
  $('btnResign').disabled = !isPlayer || OR.status !== 'playing';
  $('btnDraw').disabled = !isPlayer || OR.status !== 'playing' || OR.drawOffer === OR.yourColor;
}

/* Pendule : affichage ; à zéro, une relecture fait constater la chute par le serveur. */
setInterval(async () => {
  if (currentView !== 'online' || !OR || OR.status !== 'playing' || !OR.clock || !OR.clock.running) return;
  renderServerClocks($('onlineClocks'), OR, playerNames(), orReceivedAt);
  if (!orFlagAsked && remainingMs(OR.clock, OR.clock.running, orReceivedAt, Date.now()) <= 0) {
    orFlagAsked = true;
    try { adoptOnline((await api('games', 'get', { id: OR.id })).game); } catch (e) { /* prochain tic */ }
  }
}, 300);

$('btnResign').addEventListener('click', async () => {
  if (!OR || OR.status !== 'playing') return;
  if (await askConfirm('Abandonner ?', 'La partie sera comptée perdue.')) onlineRequest('resign');
});
$('btnDraw').addEventListener('click', () => onlineRequest('offerDraw'));
$('btnOnlinePgn').addEventListener('click', async () => {
  if (!OR || !OR.moves.length) return;
  try { await downloadPgn(OR.id); } catch (e) { alert(e.message); }
});

/* -------- Commentaires en direct sur la partie -------- */
async function syncGameChat() {
  if (!onlineId) return;
  const gameId = onlineId;
  let res;
  try { res = await api('game-comments', 'list', lastChatId === null ? { gameId } : { gameId, known: lastChatId }); } catch (e) { return; }
  if (res.unchanged || gameId !== onlineId) return;
  const last = res.comments.length ? res.comments[res.comments.length - 1].id : 0;
  if (last === lastChatId) return;
  lastChatId = last;
  renderChatLog($('gameChatLog'), chatEntries(res.comments));
}
$('gameChatSend').addEventListener('click', sendGameChat);
$('gameChatInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendGameChat(); });
async function sendGameChat() {
  if (!requirePseudo() || !onlineId) return;
  const v = $('gameChatInput').value.trim();
  if (!v) return;
  $('gameChatInput').value = '';
  /* Commentaire refusé : le texte revient dans le champ. */
  try { await api('game-comments', 'send', { gameId: onlineId, text: v }); } catch (e) { if (!$('gameChatInput').value) $('gameChatInput').value = v; alert(e.message); }
  await syncGameChat();
}
