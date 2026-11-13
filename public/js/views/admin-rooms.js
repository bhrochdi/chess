'use strict';
/* -------- Gestion et modération des salons — api/rooms.php, journal tenu par le serveur -------- */
let admModRoomId = 'general';

/* Action d'administration sur les salons ; le message d'erreur du serveur est montré tel quel. */
async function roomAdmin(action, payload) {
  try { await api('rooms', action, payload); return true; }
  catch (e) { alert(e.message); return false; }
  finally { refreshAdmin(); }
}

function renderAdminRooms(rooms) {
  const tb = $('admRooms');
  tb.innerHTML = '';
  for (const r of rooms) {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td class="small">' + r.position + '</td>' +
      '<td><strong>' + esc(r.name) + '</strong>' + (r.isDefault ? ' <span class="role-badge">DÉFAUT</span>' : '') + '</td>' +
      '<td class="small">' + esc(r.description || '—') + '</td>' +
      '<td class="small">' + (r.archived ? '<span class="ban-badge">ARCHIVÉ</span>' : 'Actif') + '</td>';
    const td = document.createElement('td');
    td.className = 'actions';
    td.appendChild(adminBtn('↑', () => roomAdmin('move', { roomId: r.id, direction: 'up' })));
    td.appendChild(adminBtn('↓', () => roomAdmin('move', { roomId: r.id, direction: 'down' })));
    td.appendChild(adminBtn('Renommer', () => renameRoom(r)));
    if (!r.isDefault) {
      td.appendChild(adminBtn(r.archived ? 'Désarchiver' : 'Archiver', () => roomAdmin('archive', { roomId: r.id, archived: !r.archived })));
      td.appendChild(adminBtn('Supprimer', () => deleteRoom(r), 'danger'));
    }
    tr.appendChild(td);
    tb.appendChild(tr);
  }
}

$('admRoomCreate').addEventListener('click', async () => {
  const msg = $('admRoomMsg');
  try {
    await api('rooms', 'create', { name: $('admRoomName').value.trim(), description: $('admRoomDesc').value.trim() });
    $('admRoomName').value = ''; $('admRoomDesc').value = ''; msg.textContent = '';
  } catch (e) {
    msg.textContent = e.message;
  }
  refreshAdmin();
});

async function renameRoom(r) {
  const nv = prompt('Nouveau nom du salon :', r.name);
  if (nv !== null) roomAdmin('rename', { roomId: r.id, name: nv.trim() });
}

async function deleteRoom(r) {
  const ok = await askConfirm('Supprimer le salon ?', 'Le salon « ' + r.name + ' » et tous ses messages seront supprimés.');
  if (ok) roomAdmin('delete', { roomId: r.id });
}

/* -------- Modération (salon choisi, archivés compris) -------- */
function populateModRoomSelector(rooms) {
  if (!rooms.some(r => r.id === admModRoomId)) admModRoomId = rooms[0] ? rooms[0].id : 'general';
  const sel = $('admModRoom');
  sel.innerHTML = '';
  for (const r of rooms) {
    const o = document.createElement('option');
    o.value = r.id;
    o.textContent = r.name + (r.archived ? ' (archivé)' : '');
    if (r.id === admModRoomId) o.selected = true;
    sel.appendChild(o);
  }
}

async function renderAdminSalon() {
  let messages;
  try { messages = (await api('rooms', 'messages', { roomId: admModRoomId })).messages; } catch (e) { return; }
  const box = $('admSalon');
  box.innerHTML = '';
  for (const m of messages.slice(-30)) {
    const d = document.createElement('div');
    d.className = 'item';
    d.innerHTML = '<span class="small"><strong>' + esc(m.author.pseudo) + '</strong> · ' + fmtTime(m.createdAt) +
      ' — ' + esc(String(m.text).slice(0, 60)) + '</span>';
    d.appendChild(adminBtn('✕', () => roomAdmin('deleteMessage', { roomId: admModRoomId, messageId: m.id }), 'danger'));
    box.appendChild(d);
  }
}

$('admModRoom').addEventListener('change', e => {
  admModRoomId = e.target.value;
  renderAdminSalon();
});

$('admClearSalon').addEventListener('click', async () => {
  const name = $('admModRoom').selectedOptions[0] ? $('admModRoom').selectedOptions[0].textContent : admModRoomId;
  const ok = await askConfirm('Vider le salon ?', 'Tous les messages du salon « ' + name + ' » seront supprimés.');
  if (ok) roomAdmin('clear', { roomId: admModRoomId });
});
