'use strict';
/* Anti-flash : applique le thème mémorisé avant le premier affichage. Chargé de façon synchrone dans
   <head>, avant les feuilles de style ; fichier séparé plutôt que script en ligne, que la politique de
   sécurité du contenu (CSP) interdit. */
(function () {
  try {
    document.documentElement.setAttribute('data-theme', localStorage.getItem('lecercle:theme') || 'bois');
  } catch (e) {
    document.documentElement.setAttribute('data-theme', 'bois');
  }
})();
