'use strict';
/* ===================== État de partie reçu du serveur =====================
   Fonctions pures : pendule affichée, choix de l'état le plus récent, textes de fin. */

/* Temps restant d'un camp à l'instant now, à partir de la pendule reçue à receivedAt (ms). */
function remainingMs(clock, color, receivedAt, now) {
  if (!clock) return null;
  const ms = color === 'w' ? clock.whiteMs : clock.blackMs;
  return clock.running === color ? Math.max(0, ms - (now - receivedAt)) : ms;
}

/* Vrai si l'état a est plus récent que b (une réponse en retard ne fait pas reculer l'échiquier). */
function newer(a, b) {
  if (!b) return true;
  return a.moves.length > b.moves.length || a.status !== b.status;
}

function uciOf(from, to, promo) {
  const sq = p => FILES[p.c] + (8 - p.r);
  return sq(from) + sq(to) + (promo ? promo.toLowerCase() : '');
}

/* Nom d'un camp : le pseudo en ligne, « Les Blancs » / « Les Noirs » sinon. */
function sideName(game, color) {
  const seat = color === 'w' ? game.white : game.black;
  if (game.mode === 'online' && seat) return { name: seat.pseudo, plural: false };
  return { name: color === 'w' ? 'Les Blancs' : 'Les Noirs', plural: true };
}

/* Titre et détail de la fin de partie, avec les formulations de l'ancien front. */
function endText(game) {
  const winnerColor = game.result === '1-0' ? 'w' : game.result === '0-1' ? 'b' : null;
  const winner = winnerColor ? sideName(game, winnerColor) : null;
  const loser = winnerColor ? sideName(game, winnerColor === 'w' ? 'b' : 'w') : null;
  const verb = (who, one, many) => who.name + ' ' + (who.plural ? many : one);
  switch (game.reason) {
    case 'mat':
      return { title: 'Échec et mat', detail: verb(winner, 'remporte', 'remportent') + ' la partie en ' + Math.ceil(game.moves.length / 2) + ' coups.' };
    case 'pat': return { title: 'Pat', detail: 'Aucun coup légal : la partie est nulle.' };
    case '50coups': return { title: 'Partie nulle', detail: 'Règle des 50 coups : 50 coups sans prise ni mouvement de pion.' };
    case 'repetition': return { title: 'Partie nulle', detail: 'Triple répétition de la même position.' };
    case 'materiel': return { title: 'Partie nulle', detail: 'Matériel insuffisant pour mater.' };
    case 'accord': return { title: 'Partie nulle', detail: 'Nulle par accord mutuel.' };
    case 'abandon': return { title: 'Abandon', detail: verb(loser, 'abandonne', 'abandonnent') + '. ' + verb(winner, 'gagne', 'gagnent') + '.' };
    case 'temps': return { title: 'Temps écoulé', detail: verb(winner, 'gagne', 'gagnent') + ' au temps.' };
    case 'admin': return { title: 'Partie close', detail: 'Clôturée par l’administration.' };
    default: return { title: 'Partie terminée', detail: '' };
  }
}
