# Contribuer à Le Cercle

Merci de votre aide. Les rapports de bugs, les idées et les pull requests sont
tous bienvenus.

## Signaler un bug ou demander une fonctionnalité

Ouvrez un [ticket](https://github.com/bhrochdi/chess/issues/new) pour
un bug ou une demande de fonctionnalité.

Ne signalez pas les problèmes de sécurité dans un ticket public.
[SECURITY.md](SECURITY.md) explique comment les signaler en privé.

## Proposer une modification

1. Forkez le dépôt et créez une branche à partir de `main`.
2. Lancez les tests :

   ```bash
   node tests/front/unit/run.mjs
   D="docker compose run --rm --no-deps --user $(id -u):$(id -g) -e COMPOSER_HOME=/tmp/composer web"
   $D composer install      # une seule fois, sur un clone neuf (crée vendor/)
   $D composer test         # tests PHPUnit
   $D composer lint         # PHP-CS-Fixer puis PHPStan
   DI="docker compose run --rm --user $(id -u):$(id -g) -e COMPOSER_HOME=/tmp/composer web"
   $DI composer test:integration   # tests contre MySQL (base le_cercle_test)
   ```

   Un contrôleur va dans `src/Infra/Http/Api/`, son point d'entrée dans `api/` et
   son contrôle HTTP dans `tests/http/smoke.d/` (`tests/http/smoke.d/README.md`).

3. Ajoutez ou mettez à jour les tests de ce que vous modifiez.
4. Mettez à jour la documentation quand le comportement, les options de
   construction ou l'API publique changent.
5. Ouvrez une pull request vers `main`.

## Commits

Conventional Commits : `<type>(portée optionnelle): <description>` avec les
types `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`,
`chore`, `revert`.

## Code de conduite

Toute personne qui participe respecte le [code de conduite](CODE_OF_CONDUCT.md).
