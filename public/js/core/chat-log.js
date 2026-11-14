'use strict';

function renderChatLog(el, log) {
  el.innerHTML = '';
  for (const m of log) {
    const d = document.createElement('div');
    if (m.sys) {
      d.className = 'msg sys';
      d.textContent = '— ' + m.text;
    } else {
      d.className = 'msg';
      d.innerHTML = '<span class="from' + (m.from === pseudo ? ' me' : '') + '">' + esc(m.from || '?') +
        '</span><span class="time">' + fmtTime(m.ts) + '</span><br>' + esc(m.text);
    }
    el.appendChild(d);
  }
  el.scrollTop = el.scrollHeight;
}

/* Messages du serveur { author: { pseudo }, text, createdAt } au format de renderChatLog. */
function chatEntries(messages) {
  return messages.map(m => ({ from: m.author.pseudo, text: m.text, ts: m.createdAt }));
}
