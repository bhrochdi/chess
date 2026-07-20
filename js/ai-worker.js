/* Worker de calcul IA : charge le moteur pur et repond aux demandes de coup. */
importScripts('engine.js');
onmessage = function(e){ var d = e.data; var m = aiBestMove(d.state, d.depth, d.fuzz); postMessage({ move: m, token: d.token }); };
