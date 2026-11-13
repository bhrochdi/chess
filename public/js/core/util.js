'use strict';

/* ===================== Utilitaires ===================== */
const $ = id => document.getElementById(id);
const GLYPH = { w: { K:'♔', Q:'♕', R:'♖', B:'♗', N:'♘', P:'♙' }, b: { K:'♚', Q:'♛', R:'♜', B:'♝', N:'♟'.replace('♟','♞'), P:'♟' } };
GLYPH.b.N = '♞';
const EN2FR = { K:'R', Q:'D', R:'T', B:'F', N:'C' };
const sanFr = san => san.replace(/[KQRBN]/g, ch => EN2FR[ch]);
const esc = t => { const d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
const fmtTime = ts => new Date(ts).toLocaleTimeString('fr-FR', { hour:'2-digit', minute:'2-digit' });
const fmtClock = sec => {
  if (sec == null) return '—';
  sec = Math.max(0, Math.ceil(sec));
  const m = Math.floor(sec / 60), s = sec % 60;
  return m + ':' + String(s).padStart(2, '0');
};
function tinySound() {
  try {
    const ctx = tinySound.ctx || (tinySound.ctx = new (window.AudioContext || window.webkitAudioContext)());
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.frequency.value = 520; o.type = 'sine';
    g.gain.setValueAtTime(0.06, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.12);
    o.connect(g); g.connect(ctx.destination);
    o.start(); o.stop(ctx.currentTime + 0.13);
  } catch (e) { /* silencieux */ }
}

