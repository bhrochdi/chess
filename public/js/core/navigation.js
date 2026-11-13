'use strict';

/* ===================== Navigation ===================== */
let currentView = 'home';
document.querySelectorAll('nav button').forEach(b => {
  b.addEventListener('click', () => switchView(b.dataset.view));
});
function switchView(v) {
  currentView = v;
  document.querySelectorAll('nav button').forEach(x => x.classList.toggle('active', x.dataset.view === v));
  document.querySelectorAll('.view').forEach(x => x.classList.remove('active'));
  $('view-' + v).classList.add('active');
  if (v === 'home') refreshLobby();
  if (v === 'local') enterLocal();
  if (v === 'salon') { updateSalonBadge(0); refreshSalon(); }
  if (v === 'dm') refreshThreads();
  if (v === 'tournois') refreshTournois();
  if (v === 'admin') refreshAdmin();
}

