test('position initiale', () => {
  const p = parseFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
  assertEqual(p.turn, 'w');
  assertEqual(p.board[0][4], { col: 'b', t: 'K' }, 'roi noir en e8');
  assertEqual(p.board[7][3], { col: 'w', t: 'Q' }, 'dame blanche en d1');
  assertEqual(p.board[4][4], null, 'e4 vide');
});

test('trait aux noirs et cases vides comptées', () => {
  const p = parseFen('rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1');
  assertEqual(p.turn, 'b');
  assertEqual(p.board[4][4], { col: 'w', t: 'P' }, 'pion en e4');
  assertEqual(p.board[6][4], null, 'e2 vide');
});

test('carré UCI vers ligne et colonne', () => {
  assertEqual(squareOf('e2'), { r: 6, c: 4 });
  assertEqual(squareOf('a8'), { r: 0, c: 0 });
});
