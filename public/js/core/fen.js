'use strict';
/* ===================== Lecture FEN (affichage seulement) =====================
   Le serveur envoie la position en FEN ; le front ne fait que la dessiner. */
const FILES = 'abcdefgh';

/* Plateau 8×8 (ligne 0 = 8e rangée) de { col, t } ou null, et trait. */
function parseFen(fen) {
  const [placement, turn] = fen.split(' ');
  const board = placement.split('/').map(rank => {
    const row = [];
    for (const ch of rank) {
      if (/\d/.test(ch)) { for (let i = 0; i < +ch; i++) row.push(null); }
      else row.push({ col: ch === ch.toUpperCase() ? 'w' : 'b', t: ch.toUpperCase() });
    }
    return row;
  });
  return { board, turn };
}

/* « e2 » → { r: 6, c: 4 } */
function squareOf(name) {
  return { r: 8 - +name[1], c: FILES.indexOf(name[0]) };
}
