'use strict';
/* ============================================================
   SALONS — lecture et envoi (api/rooms.php)
============================================================ */
let salonRoomId = 'general';
let salonLastId = null;      // dernier message affiché du salon actif
let salonSeenId = null;      // dernier message vu dans la vue Salon (badge)

function populateSalonSelector(rooms) {
  if (!rooms.some(r => r.id === salonRoomId)) salonRoomId = rooms.some(r => r.id === 'general') ? 'general' : (rooms[0] ? rooms[0].id : 'general');
  const sel = $('salonRoomSel');
  sel.innerHTML = '';
  for (const r of rooms) {
    const o = document.createElement('option');
    o.value = r.id;
    o.textContent = r.name;
    if (r.id === salonRoomId) o.selected = true;
    sel.appendChild(o);
  }
  const active = rooms.find(r => r.id === salonRoomId);
  $('salonDesc').textContent = active ? active.description : '';
}

async function refreshSalon() {
  let messages;
  try {
    populateSalonSelector((await api('rooms', 'list')).rooms);
    messages = (await api('rooms', 'messages', { roomId: salonRoomId })).messages;
  } catch (e) { return; }
  const last = messages.length ? messages[messages.length - 1].id : null;
  if (last !== salonLastId) {
    salonLastId = last;
    renderChatLog($('salonLog'), chatEntries(messages));
  }
  if (currentView === 'salon') salonSeenId = last;
  else if (salonSeenId !== null) updateSalonBadge(messages.filter(m => m.id > salonSeenId).length);
  else salonSeenId = last;
  $('salonOnline').textContent = '· ' + (await onlinePlayers()).length + ' en ligne';
}

function updateSalonBadge(n) {
  const b = $('salonBadge');
  if (n > 0 && currentView !== 'salon') { b.textContent = n > 9 ? '9+' : n; b.hidden = false; }
  else b.hidden = true;
}
setInterval(() => { if (!document.hidden && (currentView === 'salon' || currentView === 'home')) refreshSalon(); }, 3000);

/* Changement de salon : on repart d'une base propre (pas de faux badge). */
$('salonRoomSel').addEventListener('change', e => {
  salonRoomId = e.target.value;
  salonLastId = null;
  salonSeenId = null;
  $('salonLog').innerHTML = '';
  updateSalonBadge(0);
  refreshSalon();
});
$('salonSend').addEventListener('click', sendSalon);
$('salonInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendSalon(); });
async function sendSalon() {
  if (!requirePseudo()) return;
  const v = $('salonInput').value.trim();
  if (!v) return;
  $('salonInput').value = '';
  try { await api('rooms', 'send', { roomId: salonRoomId, text: v }); } catch (e) { alert(e.message); }
  refreshSalon();
}
