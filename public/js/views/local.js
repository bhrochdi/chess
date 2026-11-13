'use strict';
/* ============================================================
   JEU LOCAL & CONTRE L'ORDINATEUR — parties tenues par le serveur
============================================================ */
const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
let LG = null;              // partie reçue du serveur
/* Position de départ affichée tant qu'aucune partie n'existe. */
const LOCAL_EMPTY = { fen: START_FEN, moves: [], legalMoves: [], captured: { w: [], b: [] }, clock: null, status: 'open', inCheck: false, lastMove: null };
let lgReceivedAt = 0;       // Date.now() à la réception de LG (décompte de la pendule)
let localFlipped = false;
let localBusy = false;      // requête en cours : l'échiquier attend la réponse
let flagAsked = false;      // une seule relecture quand la pendule affiche 0

const localBoard = ServerBoard($('localBoard'), {
  getGame: () => LG || LOCAL_EMPTY,
  flipped: () => localFlipped,
  canMove: () => !!LG && !localBusy && LG.yourColor !== null,
  onMove: uci => localRequest('move', { uci, ply: LG.moves.length })
});

function localSettings() {
  const mode = $('localMode').value;
  const tcv = $('localTc').value;
  return {
    mode: mode === 'human' ? 'local' : 'ai',
    level: mode === 'human' ? null : mode,
    tc: tcv === 'inf' ? null : { base: +tcv.split('|')[0], inc: +tcv.split('|')[1] }
  };
}

function adopt(game) {
  const wasPlaying = LG && LG.id === game.id && LG.status === 'playing';
  LG = game;
  lgReceivedAt = Date.now();
  flagAsked = false;
  localBoard.clearSel();
  renderLocal();
  if (wasPlaying && game.status === 'finished') showServerEnd(game);
}

/* Action sur la partie courante ; une partie qui a avancé ailleurs est relue. */
async function localRequest(action, payload = {}) {
  if (!LG || localBusy) return;
  localBusy = true;
  renderLocal();
  try {
    adopt((await api('games', action, Object.assign({ id: LG.id }, payload))).game);
  } catch (e) {
    if (e.status === 409) { try { adopt((await api('games', 'get', { id: LG.id })).game); } catch (_) { /* reste affichée */ } }
    if (e.status !== 409 || !/avancé/.test(e.message)) alert(e.message);
  } finally {
    localBusy = false;
    renderLocal();
  }
}

function renderLocal() {
  if (!LG) {
    localBoard.render();
    renderServerSide($('view-local'), LOCAL_EMPTY);
    renderServerMoves($('localMoves'), LOCAL_EMPTY);
    $('localClocks').innerHTML = '';
    $('localStatus').textContent = 'Connecte-toi ou joue en invité, puis clique sur « Nouvelle partie ».';
    $('btnLocalUndo').disabled = true;
    return;
  }
  localBoard.render();
  renderServerSide($('view-local'), LG);
  renderServerMoves($('localMoves'), LG);
  renderServerClocks($('localClocks'), LG, { w: 'Blancs', b: LG.mode === 'ai' ? 'Ordinateur' : 'Noirs' }, lgReceivedAt);
  const st = $('localStatus');
  if (LG.status === 'finished') {
    const t = endText(LG);
    st.innerHTML = '<strong>' + esc(t.title) + '.</strong> ' + esc(t.detail);
  } else {
    const turn = parseFen(LG.fen).turn;
    const colDot = turn === 'w' ? '#faf6ec' : '#1c1611';
    const name = turn === 'w' ? 'Trait aux Blancs' : 'Trait aux Noirs';
    const chk = LG.inCheck ? ' — <strong style="color:var(--check)">Échec !</strong>' : '';
    const think = localBusy && LG.mode === 'ai' ? ' <em class="small">(l’ordinateur réfléchit…)</em>' : '';
    st.innerHTML = '<span class="turn-dot" style="background:' + colDot + '"></span>' + name + chk + think;
  }
  $('btnLocalUndo').disabled = localBusy || LG.moves.length === 0;
}

async function localNew() {
  if (!requirePseudo()) return;
  const s = localSettings();
  try {
    LG = null;
    $('endOverlay').classList.remove('show');
    adopt((await api('games', 'create', { mode: s.mode, level: s.level, tc: s.tc })).game);
  } catch (e) {
    alert(e.message);
  }
}

/* À l'ouverture de la vue : reprend la dernière partie locale ou contre l'ordinateur, sinon en crée une. */
async function enterLocal() {
  if (LG || !currentUser) { renderLocal(); return; }
  try {
    const mine = (await api('games', 'mine')).games.filter(g => g.mode === 'local' || g.mode === 'ai');
    if (!mine.length) { await localNew(); return; }
    const game = (await api('games', 'get', { id: mine[0].id })).game;
    $('localMode').value = game.mode === 'ai' ? game.level : 'human';
    $('localTc').value = game.tc ? game.tc.base + '|' + game.tc.inc : 'inf';
    adopt(game);
  } catch (e) {
    renderLocal();
  }
}

$('btnLocalNew').addEventListener('click', localNew);
$('localMode').addEventListener('change', () => { if (LG) localNew(); });
$('localTc').addEventListener('change', () => { if (LG) localNew(); });
$('btnLocalFlip').addEventListener('click', () => { localFlipped = !localFlipped; renderLocal(); });
$('btnLocalUndo').addEventListener('click', () => {
  $('endOverlay').classList.remove('show');
  localRequest('undo');
});
$('btnLocalPgn').addEventListener('click', async () => {
  if (!LG || !LG.moves.length) return;
  try { await downloadPgn(LG.id); } catch (e) { alert(e.message); }
});

/* Pendule : affichage toutes les 250 ms ; à zéro, une relecture fait constater la chute par le serveur. */
setInterval(async () => {
  if (currentView !== 'local' || !LG || LG.status !== 'playing' || !LG.clock || !LG.clock.running) return;
  renderServerClocks($('localClocks'), LG, { w: 'Blancs', b: LG.mode === 'ai' ? 'Ordinateur' : 'Noirs' }, lgReceivedAt);
  if (!flagAsked && remainingMs(LG.clock, LG.clock.running, lgReceivedAt, Date.now()) <= 0) {
    flagAsked = true;
    try { adopt((await api('games', 'get', { id: LG.id })).game); } catch (e) { /* prochain tic */ }
  }
}, 250);

renderLocal();
