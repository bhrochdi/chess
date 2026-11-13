'use strict';
/* ============================================================
   COMPTES — connexion, inscription, invités (api/auth.php)
   Le serveur décide : hachage, sessions, suspension, rôle, statistiques.
============================================================ */
let pseudo = null;
let currentUser = null;     // compte connecté tel que le serveur le décrit (invité compris)
let authMode = 'login';     // 'login' | 'register'

function refreshProfileUI() {
  if (currentUser && currentUser.provider !== 'guest') {
    const s = currentUser.stats || { w: 0, l: 0, d: 0 };
    const role = currentUser.role === 'admin' ? ' <span class="role-badge">ADMIN</span>' : '';
    $('profileLine').innerHTML = 'Connecté : <strong class="pseudo-tag">' + esc(pseudo) + '</strong>' + role + ' ' +
      '<span class="small">(' + esc(currentUser.email || '—') + ') · ' +
      s.w + ' V / ' + s.l + ' D / ' + s.d + ' N</span>';
    $('btnAuth').style.display = 'none';
    $('btnLogout').style.display = '';
  } else if (currentUser) {
    $('profileLine').innerHTML = 'Invité : <strong class="pseudo-tag">' + esc(pseudo) + '</strong> ' +
      '<span class="small">(crée un compte pour conserver tes statistiques)</span>';
    $('btnAuth').style.display = '';
    $('btnAuth').textContent = 'Créer un compte / Connexion';
    $('btnLogout').style.display = '';
  } else {
    $('profileLine').textContent = 'Connecte-toi pour accéder aux fonctions en ligne.';
    $('btnAuth').style.display = '';
    $('btnAuth').textContent = 'Connexion / Créer un compte';
    $('btnLogout').style.display = 'none';
  }
  $('navAdmin').style.display = (currentUser && currentUser.role === 'admin') ? '' : 'none';
}

/* Vrai si un compte (inscrit ou invité) est connecté ; sinon ouvre la fenêtre de connexion. */
function requirePseudo() {
  if (!currentUser) { openAuth(); return false; }
  if (currentUser.banned) { alert('Ton compte est suspendu par l’administration.'); return false; }
  return true;
}

function openAuth() {
  setAuthMode('login');
  $('authOverlay').classList.add('show');
  setTimeout(() => $('authPseudo').focus(), 60);
}

function setAuthMode(m) {
  authMode = m;
  $('tabLogin').classList.toggle('active', m === 'login');
  $('tabRegister').classList.toggle('active', m === 'register');
  $('fEmail').style.display = m === 'register' ? '' : 'none';
  $('authPseudoLabel').textContent = m === 'register' ? 'Pseudo (2–16 car. : lettres, chiffres, - _)' : 'Pseudo ou e-mail';
  $('authSubmit').textContent = m === 'register' ? 'Créer mon compte' : 'Se connecter';
  $('authMsg').textContent = '';
}
$('tabLogin').addEventListener('click', () => setAuthMode('login'));
$('tabRegister').addEventListener('click', () => setAuthMode('register'));
$('btnAuth').addEventListener('click', openAuth);
$('authClose').addEventListener('click', () => $('authOverlay').classList.remove('show'));

function finishLogin(user) {
  currentUser = user;
  pseudo = user.pseudo;
  $('authOverlay').classList.remove('show');
  $('authPass').value = '';
  refreshProfileUI();
  heartbeat();
  refreshThreads();
  refreshLobby();
  if (currentView === 'local') { LG = null; enterLocal(); }
}

async function authSubmitAction() {
  const msg = $('authMsg');
  msg.textContent = '';
  const pass = $('authPass').value;
  try {
    const r = authMode === 'register'
      ? await api('auth', 'register', { pseudo: $('authPseudo').value.trim(), email: $('authEmail').value.trim(), password: pass })
      : await api('auth', 'login', { identifiant: $('authPseudo').value.trim(), password: pass });
    finishLogin(r.user);
  } catch (e) {
    msg.textContent = e.message;
  }
}
$('authSubmit').addEventListener('click', authSubmitAction);
$('authPass').addEventListener('keydown', e => { if (e.key === 'Enter') authSubmitAction(); });

/* -------- Invité : un vrai compte serveur, sans e-mail ni mot de passe -------- */
$('authGuest').addEventListener('click', async () => {
  const chosen = $('authPseudo').value.trim();
  try {
    finishLogin((await api('auth', 'guest', chosen ? { pseudo: chosen } : {})).user);
  } catch (e) {
    $('authMsg').textContent = e.message;
  }
});

/* -------- Déconnexion -------- */
$('btnLogout').addEventListener('click', async () => {
  try { await api('auth', 'logout'); } catch (e) { /* session déjà close */ }
  pseudo = null;
  currentUser = null;
  LG = null;
  renderLocal();
  refreshProfileUI();
  refreshLobby();
});

/* -------- Session (reconnexion automatique) -------- */
async function loadSession() {
  try {
    const r = await api('auth', 'me');
    if (r.user) { currentUser = r.user; pseudo = r.user.pseudo; }
  } catch (e) { /* serveur injoignable : reste déconnecté */ }
  refreshProfileUI();
}
