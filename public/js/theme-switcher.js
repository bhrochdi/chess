(function(){
  var KEY = 'lecercle:theme';
  var DEFAULT = 'bois';
  var VALID = ['bois','clair','marbre','nocturne'];

  function apply(theme){
    if (VALID.indexOf(theme) === -1) theme = DEFAULT;
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(KEY, theme); } catch(e){}
    document.querySelectorAll('.theme-opt').forEach(function(b){
      b.classList.toggle('active', b.getAttribute('data-set-theme') === theme);
    });
  }

  // Brancher les pastilles
  document.querySelectorAll('.theme-opt').forEach(function(b){
    b.addEventListener('click', function(){ apply(b.getAttribute('data-set-theme')); });
  });

  // Restaurer le choix précédent (ou défaut)
  var saved;
  try { saved = localStorage.getItem(KEY); } catch(e){}
  apply(saved || DEFAULT);
})();
