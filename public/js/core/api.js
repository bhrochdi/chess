'use strict';
/* ===================== Client d'API =====================
   Toute requête au serveur passe par api(ressource, action, données). */

class ApiError extends Error {
  constructor(status, message) {
    super(message);
    this.status = status;
  }
}

/* POST api/<resource>.php { action, ... } → corps de la réponse ; ApiError sinon. */
async function api(resource, action, payload = {}) {
  let res;
  try {
    res = await fetch('api/' + resource + '.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ action }, payload))
    });
  } catch (e) {
    throw new ApiError(0, 'Serveur injoignable.');
  }
  let body = null;
  try { body = await res.json(); } catch (e) { /* corps illisible */ }
  if (!res.ok || !body || body.ok !== true) {
    throw new ApiError(res.status, (body && body.error) || 'Erreur du serveur (' + res.status + ').');
  }
  return body;
}
