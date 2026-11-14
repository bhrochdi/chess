'use strict';
/* ===================== Échiquier piloté par le serveur =====================
   Le serveur décide (coups légaux, pendule, fin) ; ce fichier dessine l'état reçu. */

const PIECE_VALUE = { P: 1, N: 3, B: 3, R: 5, Q: 9, K: 0 };

/* opts : { getGame, canMove() → bool, onMove(uci), flipped() } */
function ServerBoard(boardEl, opts) {
  const frame = boardEl.closest('.board-frame');
  const ranksEl = frame.querySelector('.ranks');
  const filesEl = frame.querySelector('.files');
  let selected = null;
  let targets = [];       // coups UCI partant de la case choisie

  function coords() {
    ranksEl.innerHTML = ''; filesEl.innerHTML = '';
    for (let i = 0; i < 8; i++) {
      const r = document.createElement('span');
      r.textContent = opts.flipped() ? i + 1 : 8 - i;
      ranksEl.appendChild(r);
      const f = document.createElement('span');
      f.textContent = FILES[opts.flipped() ? 7 - i : i];
      filesEl.appendChild(f);
    }
  }

  const nameOf = (r, c) => FILES[c] + (8 - r);

  async function onSquare(r, c) {
    const game = opts.getGame();
    if (!game || game.status !== 'playing' || !opts.canMove()) return;
    const here = nameOf(r, c);
    const hits = targets.filter(u => u.slice(2, 4) === here);
    if (hits.length) {
      selected = null; targets = [];
      let uci = hits[0].slice(0, 4);
      if (hits.some(u => u.length === 5)) uci += (await askPromotion(parseFen(game.fen).turn)).toLowerCase();
      opts.onMove(uci);
      return;
    }
    const from = game.legalMoves.filter(u => u.slice(0, 2) === here);
    selected = from.length ? { r, c } : null;
    targets = from;
    render();
  }

  function render() {
    const game = opts.getGame();
    coords();
    boardEl.innerHTML = '';
    if (!game) return;
    const pos = parseFen(game.fen);
    let king = null;
    if (game.inCheck) {
      for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
        const p = pos.board[r][c];
        if (p && p.t === 'K' && p.col === pos.turn) king = { r, c };
      }
    }
    const last = game.lastMove ? [squareOf(game.lastMove.from), squareOf(game.lastMove.to)] : [];
    for (let i = 0; i < 64; i++) {
      const vr = Math.floor(i / 8), vc = i % 8;
      const r = opts.flipped() ? 7 - vr : vr;
      const c = opts.flipped() ? 7 - vc : vc;
      const sq = document.createElement('div');
      sq.className = 'sq ' + ((r + c) % 2 === 0 ? 'light' : 'dark');
      sq.tabIndex = 0;
      sq.setAttribute('role', 'gridcell');
      const p = pos.board[r][c];
      if (p) {
        sq.textContent = GLYPH[p.col][p.t];
        sq.classList.add(p.col === 'w' ? 'piece-w' : 'piece-b');
      }
      if (last.some(s => s.r === r && s.c === c)) sq.classList.add('lastmove');
      if (selected && selected.r === r && selected.c === c) sq.classList.add('selected');
      if (king && king.r === r && king.c === c) sq.classList.add('in-check');
      if (targets.some(u => u.slice(2, 4) === nameOf(r, c))) sq.classList.add(p ? 'capture-hint' : 'move-hint');
      const act = () => onSquare(r, c);
      sq.addEventListener('click', act);
      sq.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); act(); } });
      boardEl.appendChild(sq);
    }
  }

  return { render, clearSel: () => { selected = null; targets = []; } };
}

/* Pièces prises : game.captured.w = lettres FEN des pièces prises par les Blancs. */
function renderServerSide(rootEl, game) {
  const pieces = letters => letters.map(l => ({ col: l === l.toUpperCase() ? 'w' : 'b', t: l.toUpperCase() }));
  const byWhite = pieces(game.captured.w), byBlack = pieces(game.captured.b);
  const fmt = arr => arr.map(p => GLYPH[p.col][p.t]).join('');
  rootEl.querySelector('[data-cap="w"]').textContent = fmt(byWhite);
  rootEl.querySelector('[data-cap="b"]').textContent = fmt(byBlack);
  const sum = arr => arr.reduce((a, p) => a + PIECE_VALUE[p.t], 0);
  const dw = sum(byWhite) - sum(byBlack);
  rootEl.querySelector('[data-score="w"]').textContent = dw > 0 ? '+' + dw : '';
  rootEl.querySelector('[data-score="b"]').textContent = dw < 0 ? '+' + (-dw) : '';
}

function renderServerMoves(el, game) {
  el.innerHTML = '';
  for (let i = 0; i < game.moves.length; i += 2) {
    const num = document.createElement('span');
    num.className = 'num';
    num.textContent = (i / 2 + 1) + '.';
    el.appendChild(num);
    for (let j = 0; j < 2; j++) {
      const ply = document.createElement('span');
      ply.className = 'ply';
      if (i + j === game.moves.length - 1) ply.classList.add('latest');
      ply.textContent = game.moves[i + j] ? sanFr(game.moves[i + j]) : '';
      el.appendChild(ply);
    }
  }
  el.scrollTop = el.scrollHeight;
}

/* receivedAt : Date.now() au moment où l'état a été reçu. */
function renderServerClocks(el, game, names, receivedAt) {
  el.innerHTML = '';
  for (const col of ['b', 'w']) {
    const d = document.createElement('div');
    d.className = 'clock';
    const ms = remainingMs(game.clock, col, receivedAt, Date.now());
    if (game.clock && game.status === 'playing' && game.clock.running === col) d.classList.add('running');
    if (ms !== null && ms <= 0) d.classList.add('flag');
    d.innerHTML = '<span class="who">' + esc(names[col]) + '</span>' + fmtClock(ms === null ? null : ms / 1000);
    el.appendChild(d);
  }
}

function showServerEnd(game) {
  if (game.status !== 'finished') return;
  const t = endText(game);
  $('endTitle').textContent = t.title;
  $('endText').textContent = t.detail + '  (' + game.result + ')';
  setTimeout(() => $('endOverlay').classList.add('show'), 400);
}

/* Télécharge le PGN préparé par le serveur. */
async function downloadPgn(gameId) {
  const { filename, pgn } = await api('games', 'pgn', { id: gameId });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([pgn], { type: 'application/x-chess-pgn' }));
  a.download = filename;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}
