'use strict';

/* Présence : un battement toutes les 25 s ; le serveur garde l'heure de dernière visite. */
async function heartbeat() {
  if (!currentUser) return;
  try { await api('auth', 'heartbeat'); } catch (e) { /* prochain battement */ }
}
setInterval(heartbeat, 25000);

/* Pseudos vus dans les 70 dernières secondes, triés par le serveur. */
async function onlinePlayers() {
  try { return (await api('auth', 'online')).players.map(p => p.pseudo); } catch (e) { return []; }
}
