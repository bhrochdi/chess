const clock = { whiteMs: 60000, blackMs: 30000, running: 'w', serverNow: 1000 };

test('temps restant du camp au trait décompté depuis la réception', () => {
  assertEqual(remainingMs(clock, 'w', 5000, 7500), 57500);
  assertEqual(remainingMs(clock, 'b', 5000, 7500), 30000, 'pendule arrêtée');
  assertEqual(remainingMs(clock, 'w', 5000, 500000), 0, 'jamais négatif');
  assertEqual(remainingMs(null, 'w', 0, 0), null, 'sans cadence');
});

test('état plus récent', () => {
  const a = { moves: ['e4'], status: 'playing' };
  assertEqual(newer({ moves: ['e4', 'e5'], status: 'playing' }, a), true);
  assertEqual(newer({ moves: [], status: 'playing' }, a), false, 'réponse en retard');
  assertEqual(newer({ moves: ['e4'], status: 'finished' }, a), true, 'statut changé');
  assertEqual(newer(a, null), true);
});

test('coup UCI avec promotion', () => {
  assertEqual(uciOf({ r: 6, c: 4 }, { r: 4, c: 4 }, null), 'e2e4');
  assertEqual(uciOf({ r: 1, c: 0 }, { r: 0, c: 0 }, 'Q'), 'a7a8q');
});

test('textes de fin', () => {
  const g = (reason, result, extra) => Object.assign({ mode: 'online', reason, result, moves: ['f3', 'e5', 'g4', 'Qh4#'],
    white: { pseudo: 'alice' }, black: { pseudo: 'bob' } }, extra || {});
  assertEqual(endText(g('mat', '0-1')), { title: 'Échec et mat', detail: 'bob remporte la partie en 2 coups.' });
  assertEqual(endText(g('mat', '0-1', { mode: 'local' })), { title: 'Échec et mat', detail: 'Les Noirs remportent la partie en 2 coups.' });
  assertEqual(endText(g('abandon', '1-0')), { title: 'Abandon', detail: 'bob abandonne. alice gagne.' });
  assertEqual(endText(g('temps', '1-0', { mode: 'ai' })), { title: 'Temps écoulé', detail: 'Les Blancs gagnent au temps.' });
  assertEqual(endText(g('accord', '1/2-1/2')).title, 'Partie nulle');
  assertEqual(endText(g('pat', '1/2-1/2')).title, 'Pat');
  assertEqual(endText(g('50coups', '1/2-1/2')).detail, 'Règle des 50 coups : 50 coups sans prise ni mouvement de pion.');
  assertEqual(endText(g('repetition', '1/2-1/2')).detail, 'Triple répétition de la même position.');
  assertEqual(endText(g('materiel', '1/2-1/2')).detail, 'Matériel insuffisant pour mater.');
  assertEqual(endText(g('admin', '1/2-1/2')), { title: 'Partie close', detail: 'Clôturée par l’administration.' });
});
