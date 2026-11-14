'use strict';

/* ===================== Démarrage ===================== */
(async function boot() {
  await loadSession();
  if (pseudo) { heartbeat(); refreshThreads(); }
  refreshLobby();
  refreshSalon();
})();
