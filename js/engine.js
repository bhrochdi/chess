'use strict';
const FILES = 'abcdefgh';
const VALUE = { P:1, N:3, B:3, R:5, Q:9, K:0 };
const VALUE_AI = { P:100, N:320, B:330, R:500, Q:900, K:20000 };
const PST = {
  P: [[0,0,0,0,0,0,0,0],[50,50,50,50,50,50,50,50],[10,10,20,30,30,20,10,10],[5,5,10,25,25,10,5,5],[0,0,0,20,20,0,0,0],[5,-5,-10,0,0,-10,-5,5],[5,10,10,-20,-20,10,10,5],[0,0,0,0,0,0,0,0]],
  N: [[-50,-40,-30,-30,-30,-30,-40,-50],[-40,-20,0,0,0,0,-20,-40],[-30,0,10,15,15,10,0,-30],[-30,5,15,20,20,15,5,-30],[-30,0,15,20,20,15,0,-30],[-30,5,10,15,15,10,5,-30],[-40,-20,0,5,5,0,-20,-40],[-50,-40,-30,-30,-30,-30,-40,-50]],
  B: [[-20,-10,-10,-10,-10,-10,-10,-20],[-10,0,0,0,0,0,0,-10],[-10,0,5,10,10,5,0,-10],[-10,5,5,10,10,5,5,-10],[-10,0,10,10,10,10,0,-10],[-10,10,10,10,10,10,10,-10],[-10,5,0,0,0,0,5,-10],[-20,-10,-10,-10,-10,-10,-10,-20]],
  R: [[0,0,0,0,0,0,0,0],[5,10,10,10,10,10,10,5],[-5,0,0,0,0,0,0,-5],[-5,0,0,0,0,0,0,-5],[-5,0,0,0,0,0,0,-5],[-5,0,0,0,0,0,0,-5],[-5,0,0,0,0,0,0,-5],[0,0,0,5,5,0,0,0]],
  Q: [[-20,-10,-10,-5,-5,-10,-10,-20],[-10,0,0,0,0,0,0,-10],[-10,0,5,5,5,5,0,-10],[-5,0,5,5,5,5,0,-5],[0,0,5,5,5,5,0,-5],[-10,5,5,5,5,5,0,-10],[-10,0,5,0,0,0,0,-10],[-20,-10,-10,-5,-5,-10,-10,-20]],
  K: [[-30,-40,-40,-50,-50,-40,-40,-30],[-30,-40,-40,-50,-50,-40,-40,-30],[-30,-40,-40,-50,-50,-40,-40,-30],[-30,-40,-40,-50,-50,-40,-40,-30],[-20,-30,-30,-40,-40,-30,-30,-20],[-10,-20,-20,-20,-20,-20,-20,-10],[20,20,0,0,0,0,20,20],[20,30,10,0,0,10,30,20]]
};

function initialPosition() {
  const b = Array.from({length:8}, () => Array(8).fill(null));
  const back = ['R','N','B','Q','K','B','N','R'];
  for (let c = 0; c < 8; c++) {
    b[0][c] = { t: back[c], col: 'b' };
    b[1][c] = { t: 'P', col: 'b' };
    b[6][c] = { t: 'P', col: 'w' };
    b[7][c] = { t: back[c], col: 'w' };
  }
  return { board: b, turn: 'w', castling: { wK:true, wQ:true, bK:true, bQ:true }, ep: null, halfmove: 0 };
}
function cloneState(s) { return JSON.parse(JSON.stringify(s)); }
function inside(r, c) { return r >= 0 && r < 8 && c >= 0 && c < 8; }
function opp(col) { return col === 'w' ? 'b' : 'w'; }

function pseudoMoves(s, r, c) {
  const p = s.board[r][c];
  if (!p) return [];
  const out = [];
  const add = (tr, tc, fl) => { const m = { fr:r, fc:c, tr, tc }; if (fl) Object.assign(m, fl); out.push(m); };
  const slide = dirs => {
    for (let i = 0; i < dirs.length; i++) {
      const dr = dirs[i][0], dc = dirs[i][1];
      let nr = r + dr, nc = c + dc;
      while (inside(nr, nc)) {
        const q = s.board[nr][nc];
        if (!q) add(nr, nc);
        else { if (q.col !== p.col) add(nr, nc); break; }
        nr += dr; nc += dc;
      }
    }
  };
  if (p.t === 'P') {
    const dir = p.col === 'w' ? -1 : 1;
    const start = p.col === 'w' ? 6 : 1;
    const promoRow = p.col === 'w' ? 0 : 7;
    if (inside(r + dir, c) && !s.board[r + dir][c]) {
      add(r + dir, c, { promo: r + dir === promoRow });
      if (r === start && !s.board[r + 2*dir][c]) add(r + 2*dir, c, { double:true });
    }
    const dcs = [-1, 1];
    for (let i = 0; i < 2; i++) {
      const nr = r + dir, nc = c + dcs[i];
      if (!inside(nr, nc)) continue;
      const q = s.board[nr][nc];
      if (q && q.col !== p.col) add(nr, nc, { promo: nr === promoRow });
      else if (s.ep && s.ep.r === nr && s.ep.c === nc) add(nr, nc, { ep:true });
    }
  } else if (p.t === 'N') {
    const js = [[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]];
    for (let i = 0; i < js.length; i++) {
      const nr = r + js[i][0], nc = c + js[i][1];
      if (inside(nr, nc) && (!s.board[nr][nc] || s.board[nr][nc].col !== p.col)) add(nr, nc);
    }
  } else if (p.t === 'B') slide([[-1,-1],[-1,1],[1,-1],[1,1]]);
  else if (p.t === 'R') slide([[-1,0],[1,0],[0,-1],[0,1]]);
  else if (p.t === 'Q') slide([[-1,-1],[-1,1],[1,-1],[1,1],[-1,0],[1,0],[0,-1],[0,1]]);
  else if (p.t === 'K') {
    for (let dr = -1; dr <= 1; dr++) for (let dc = -1; dc <= 1; dc++) {
      if (!dr && !dc) continue;
      const nr = r + dr, nc = c + dc;
      if (inside(nr, nc) && (!s.board[nr][nc] || s.board[nr][nc].col !== p.col)) add(nr, nc);
    }
    const row = p.col === 'w' ? 7 : 0;
    if (r === row && c === 4 && !isAttacked(s, row, 4, opp(p.col))) {
      if (s.castling[p.col + 'K'] && !s.board[row][5] && !s.board[row][6] &&
          !isAttacked(s, row, 5, opp(p.col)) && !isAttacked(s, row, 6, opp(p.col)) &&
          s.board[row][7] && s.board[row][7].t === 'R' && s.board[row][7].col === p.col)
        add(row, 6, { castle:'K' });
      if (s.castling[p.col + 'Q'] && !s.board[row][3] && !s.board[row][2] && !s.board[row][1] &&
          !isAttacked(s, row, 3, opp(p.col)) && !isAttacked(s, row, 2, opp(p.col)) &&
          s.board[row][0] && s.board[row][0].t === 'R' && s.board[row][0].col === p.col)
        add(row, 2, { castle:'Q' });
    }
  }
  return out;
}

function isAttacked(s, r, c, byCol) {
  const dir = byCol === 'w' ? 1 : -1;
  const dcs = [-1, 1];
  for (let i = 0; i < 2; i++) {
    const nr = r + dir, nc = c + dcs[i];
    if (inside(nr, nc)) {
      const q = s.board[nr][nc];
      if (q && q.col === byCol && q.t === 'P') return true;
    }
  }
  const js = [[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]];
  for (let i = 0; i < js.length; i++) {
    const nr = r + js[i][0], nc = c + js[i][1];
    if (inside(nr, nc)) {
      const q = s.board[nr][nc];
      if (q && q.col === byCol && q.t === 'N') return true;
    }
  }
  const rays = [
    { dirs: [[-1,0],[1,0],[0,-1],[0,1]], types: ['R','Q'] },
    { dirs: [[-1,-1],[-1,1],[1,-1],[1,1]], types: ['B','Q'] }
  ];
  for (let g = 0; g < 2; g++) {
    const dirs = rays[g].dirs, types = rays[g].types;
    for (let i = 0; i < 4; i++) {
      let nr = r + dirs[i][0], nc = c + dirs[i][1], dist = 1;
      while (inside(nr, nc)) {
        const q = s.board[nr][nc];
        if (q) {
          if (q.col === byCol && (types.indexOf(q.t) >= 0 || (q.t === 'K' && dist === 1))) return true;
          break;
        }
        nr += dirs[i][0]; nc += dirs[i][1]; dist++;
      }
    }
  }
  return false;
}

function findKing(s, col) {
  for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
    const p = s.board[r][c];
    if (p && p.t === 'K' && p.col === col) return { r, c };
  }
  return null;
}
function inCheck(s, col) {
  const k = findKing(s, col);
  return k ? isAttacked(s, k.r, k.c, opp(col)) : false;
}

/* Applique un coup ; renvoie la pièce capturée éventuelle. */
function applyMove(s, m, promoPiece) {
  const p = s.board[m.fr][m.fc];
  let capture = s.board[m.tr][m.tc];
  if (m.ep) { capture = s.board[m.fr][m.tc]; s.board[m.fr][m.tc] = null; }
  s.board[m.tr][m.tc] = p;
  s.board[m.fr][m.fc] = null;
  if (m.castle) {
    const row = m.fr;
    if (m.castle === 'K') { s.board[row][5] = s.board[row][7]; s.board[row][7] = null; }
    else { s.board[row][3] = s.board[row][0]; s.board[row][0] = null; }
  }
  if (m.promo) p.t = promoPiece || 'Q';
  if (p.t === 'K' || m.castle) { s.castling[p.col + 'K'] = false; s.castling[p.col + 'Q'] = false; }
  if (m.fr === 7 && m.fc === 0) s.castling.wQ = false;
  if (m.fr === 7 && m.fc === 7) s.castling.wK = false;
  if (m.fr === 0 && m.fc === 0) s.castling.bQ = false;
  if (m.fr === 0 && m.fc === 7) s.castling.bK = false;
  if (m.tr === 7 && m.tc === 0) s.castling.wQ = false;
  if (m.tr === 7 && m.tc === 7) s.castling.wK = false;
  if (m.tr === 0 && m.tc === 0) s.castling.bQ = false;
  if (m.tr === 0 && m.tc === 7) s.castling.bK = false;
  s.ep = m.double ? { r: (m.fr + m.tr) / 2, c: m.fc } : null;
  s.halfmove = (p.t === 'P' || m.promo || capture) ? 0 : s.halfmove + 1;
  s.turn = opp(s.turn);
  return capture;
}

function legalMoves(s, r, c) {
  const p = s.board[r][c];
  if (!p || p.col !== s.turn) return [];
  return pseudoMoves(s, r, c).filter(function(m) {
    const t = cloneState(s);
    applyMove(t, m);
    return !inCheck(t, p.col);
  });
}
function allLegalMoves(s, col) {
  const out = [];
  for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
    const p = s.board[r][c];
    if (p && p.col === col) {
      const pm = pseudoMoves(s, r, c);
      for (let i = 0; i < pm.length; i++) {
        const t = cloneState(s);
        applyMove(t, pm[i]);
        if (!inCheck(t, col)) out.push(pm[i]);
      }
    }
  }
  return out;
}

function positionKey(s) {
  let k = s.turn;
  for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
    const p = s.board[r][c];
    k += p ? p.col + p.t : '.';
  }
  k += (s.castling.wK?'K':'') + (s.castling.wQ?'Q':'') + (s.castling.bK?'k':'') + (s.castling.bQ?'q':'');
  k += s.ep ? FILES[s.ep.c] + s.ep.r : '-';
  return k;
}

function insufficientMaterial(s) {
  let minors = 0;
  for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
    const p = s.board[r][c];
    if (!p || p.t === 'K') continue;
    if (p.t === 'B' || p.t === 'N') minors++;
    else return false;
  }
  return minors <= 1;
}

/* SAN (lettres anglaises — standard PGN) */
function toSAN(s, m, promoPiece) {
  const p = s.board[m.fr][m.fc];
  if (m.castle) return m.castle === 'K' ? 'O-O' : 'O-O-O';
  const dest = FILES[m.tc] + (8 - m.tr);
  const isCapture = !!s.board[m.tr][m.tc] || m.ep;
  let txt;
  if (p.t === 'P') {
    txt = (isCapture ? FILES[m.fc] + 'x' : '') + dest;
    if (m.promo) txt += '=' + (promoPiece || 'Q');
  } else {
    let amb = '';
    for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
      if (r === m.fr && c === m.fc) continue;
      const q = s.board[r][c];
      if (q && q.col === p.col && q.t === p.t) {
        const lm = legalMoves(s, r, c);
        for (let i = 0; i < lm.length; i++) {
          if (lm[i].tr === m.tr && lm[i].tc === m.tc) {
            amb = (c !== m.fc) ? FILES[m.fc] : String(8 - m.fr);
          }
        }
      }
    }
    txt = p.t + amb + (isCapture ? 'x' : '') + dest;
  }
  return txt;
}

/* ---------- IA : minimax alpha-bêta ---------- */
function evaluate(s) {
  let score = 0;
  for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
    const p = s.board[r][c];
    if (!p) continue;
    const pst = p.col === 'w' ? PST[p.t][r][c] : PST[p.t][7 - r][c];
    const v = VALUE_AI[p.t] + pst;
    score += p.col === 'w' ? v : -v;
  }
  return score;
}
function orderMoves(s, moves) {
  moves.sort(function(a, b) {
    const va = s.board[a.tr][a.tc] ? VALUE_AI[s.board[a.tr][a.tc].t] : 0;
    const vb = s.board[b.tr][b.tc] ? VALUE_AI[s.board[b.tr][b.tc].t] : 0;
    return vb - va;
  });
  return moves;
}
function minimax(s, depth, alpha, beta, maximizing) {
  if (depth === 0) return evaluate(s);
  const col = maximizing ? 'w' : 'b';
  const moves = orderMoves(s, allLegalMoves(s, col));
  if (moves.length === 0) {
    if (inCheck(s, col)) return maximizing ? -99999 - depth : 99999 + depth;
    return 0;
  }
  if (maximizing) {
    let best = -Infinity;
    for (let i = 0; i < moves.length; i++) {
      const t = cloneState(s);
      applyMove(t, moves[i]);
      const v = minimax(t, depth - 1, alpha, beta, false);
      if (v > best) best = v;
      if (best > alpha) alpha = best;
      if (beta <= alpha) break;
    }
    return best;
  } else {
    let best = Infinity;
    for (let i = 0; i < moves.length; i++) {
      const t = cloneState(s);
      applyMove(t, moves[i]);
      const v = minimax(t, depth - 1, alpha, beta, true);
      if (v < best) best = v;
      if (best < beta) beta = best;
      if (beta <= alpha) break;
    }
    return best;
  }
}
function aiBestMove(s, depth, fuzz) {
  const col = s.turn;
  const moves = orderMoves(s, allLegalMoves(s, col));
  if (moves.length === 0) return null;
  let best = null, bestScore = -Infinity;
  for (let i = 0; i < moves.length; i++) {
    const t = cloneState(s);
    applyMove(t, moves[i]);
    let sc = minimax(t, depth - 1, -Infinity, Infinity, t.turn === 'w');
    if (col === 'b') sc = -sc;
    sc += (fuzz || 0) * Math.random();
    if (sc > bestScore) { bestScore = sc; best = moves[i]; }
  }
  return best;
}
