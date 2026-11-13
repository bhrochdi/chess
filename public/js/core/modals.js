'use strict';

/* ===================== Modales communes ===================== */
function askPromotion(color) {
  return new Promise(resolve => {
    const box = $('promoChoices');
    box.innerHTML = '';
    const NAMES = { Q:'Dame', R:'Tour', B:'Fou', N:'Cavalier' };
    for (const t of ['Q','R','B','N']) {
      const b = document.createElement('button');
      b.textContent = GLYPH[color][t];
      b.setAttribute('aria-label', NAMES[t]);
      b.addEventListener('click', () => {
        $('promoOverlay').classList.remove('show');
        resolve(t);
      });
      box.appendChild(b);
    }
    $('promoOverlay').classList.add('show');
  });
}
$('endClose').addEventListener('click', () => $('endOverlay').classList.remove('show'));

let confirmResolve = null;
function askConfirm(title, text) {
  return new Promise(resolve => {
    confirmResolve = resolve;
    $('confirmTitle').textContent = title;
    $('confirmText').textContent = text;
    $('confirmOverlay').classList.add('show');
  });
}
$('confirmYes').addEventListener('click', () => { $('confirmOverlay').classList.remove('show'); if (confirmResolve) { confirmResolve(true); confirmResolve = null; } });
$('confirmNo').addEventListener('click', () => { $('confirmOverlay').classList.remove('show'); if (confirmResolve) { confirmResolve(false); confirmResolve = null; } });

