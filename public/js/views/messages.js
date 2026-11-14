'use strict';
/* ============================================================
   MESSAGERIE PRIVÉE (api/direct-messages.php)
============================================================ */
let dmPeer = null;           // { id, pseudo }
let dmLastId = null;

async function refreshThreads() {
  if (!currentUser) return;
  let data;
  try { data = await api('direct-messages', 'threads'); } catch (e) { return; }
  const box = $('dmThreads');
  box.innerHTML = '';
  for (const t of data.threads) {
    const b = document.createElement('button');
    b.className = 'th' + (dmPeer && t.peer.id === dmPeer.id ? ' active' : '');
    b.innerHTML = '<span><strong>' + esc(t.peer.pseudo) + '</strong><br><span class="small">' +
      esc(String(t.last.text).slice(0, 28)) + '</span></span>' +
      (t.unread ? '<span class="badge">' + t.unread + '</span>' : '');
    b.addEventListener('click', () => openThread(t.peer.pseudo));
    box.appendChild(b);
  }
  const badge = $('dmBadge');
  if (data.unreadTotal > 0 && currentView !== 'dm') { badge.textContent = data.unreadTotal > 9 ? '9+' : data.unreadTotal; badge.hidden = false; }
  else badge.hidden = true;
}
setInterval(() => { if (!document.hidden && currentUser) refreshThreads(); }, 8000);

async function openThread(peerPseudo) {
  if (!requirePseudo()) return;
  try { dmPeer = (await api('direct-messages', 'open', { pseudo: peerPseudo })).peer; }
  catch (e) { alert(e.message); return; }
  dmLastId = null;
  $('dmTitle').textContent = 'Conversation avec ' + dmPeer.pseudo;
  $('dmInput').disabled = false;
  $('dmSend').disabled = false;
  await refreshDm();
  refreshThreads();
}

async function refreshDm() {
  if (!dmPeer || !currentUser) return;
  let messages;
  try { messages = (await api('direct-messages', 'conversation', { peerId: dmPeer.id })).messages; } catch (e) { return; }
  const last = messages.length ? messages[messages.length - 1].id : null;
  if (last === dmLastId) return;
  dmLastId = last;
  renderChatLog($('dmLog'), chatEntries(messages));
}
setInterval(() => { if (!document.hidden && currentView === 'dm' && dmPeer) refreshDm(); }, 3000);

$('dmNewBtn').addEventListener('click', () => {
  const v = $('dmNewPeer').value.trim();
  if (v) { $('dmNewPeer').value = ''; openThread(v); }
});
$('dmSend').addEventListener('click', sendDm);
$('dmInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendDm(); });
async function sendDm() {
  if (!requirePseudo() || !dmPeer) return;
  const v = $('dmInput').value.trim();
  if (!v) return;
  $('dmInput').value = '';
  try { await api('direct-messages', 'send', { peerId: dmPeer.id, text: v }); } catch (e) { alert(e.message); }
  await refreshDm();
}
