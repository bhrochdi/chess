# Handoff — Le Cercle : sélecteur de thèmes (4 thèmes commutables par l'utilisateur)

## Objectif
Ajouter au site **Le Cercle** un **sélecteur de thème** visible par l'utilisateur final, lui permettant de basculer entre **4 thèmes** d'un clic, avec mémorisation de son choix. Aucune modification de la logique de jeu.

Les 4 thèmes :
| Clé | Nom | Ambiance |
|---|---|---|
| `bois` | Bois & or | Club d'échecs, noyer sombre + or (la refonte déjà intégrée) — **défaut** |
| `clair` | Clair | Épuré, lumineux, sans-serif, accent vert sauge |
| `marbre` | Marbre & laiton | Club de luxe, marbre crème, dorures, serif |
| `nocturne` | Nocturne | Sombre et électrique, accents néon, énergie « blitz » |

---

## À propos des fichiers de ce paquet
Les fichiers HTML joints (`reference/`) sont des **références de design créées en HTML** — des maquettes qui montrent l'aspect visé, **pas du code de production à copier tel quel**. La tâche est de **reproduire ces thèmes dans le site réel** (`index.html`, monofichier vanilla HTML/CSS/JS) en suivant ses conventions existantes.

Le livrable réellement exploitable est **`themes.css`** + **`theme-switcher.html`** : ils sont écrits pour le site réel et constituent l'essentiel du travail.

## Fidélité
**Haute fidélité.** Couleurs, typographies, ombres et rayons sont définitifs (valeurs exactes dans `themes.css`). Reproduire au pixel près.

---

## Comment ça marche (architecture)
Le site utilise **déjà** des variables CSS pour tout son style (`--gold`, `--check`, `--paper`, `--sq-light`, `--font-text`…). Certaines sont même lues **en ligne dans le JS** (ex. `var(--check)` pour l'échec).

Un thème = **une redéfinition de ces mêmes variables**, scopée sur un attribut `data-theme` posé sur `<html>` :

```css
:root[data-theme="clair"]    { --gold:#5b7a4f; --bg:#f3f1ec; --font-text:'Hanken Grotesk'; … }
:root[data-theme="nocturne"] { --gold:#46e6c8; --bg:#0a0b10; --font-text:'Space Grotesk'; … }
```

Changer l'attribut `data-theme` recolore/retypographie tout le site **sans toucher au moteur**. Le JS continue de lire les `var()` et suit automatiquement.

> ⚠️ **Ne renommez aucune variable** du contrat de tokens — le JS référence certains noms en dur. Ne changez que leurs valeurs (c'est ce que font les blocs `[data-theme]`).

---

## §1–5 · Étapes d'intégration (≈ 20 min)

### §1 — Polices
`themes.css` contient déjà l'`@import` Google Fonts des 6 familles (Cormorant Garamond, Spectral, Newsreader, Hanken Grotesk, Jost, Space Grotesk, JetBrains Mono). Si vous préférez `<link>`, placez-le dans le `<head>` et retirez l'`@import`.

### §2 — Charger `themes.css`
Deux options :
- **Fichier séparé** : `<link rel="stylesheet" href="themes.css">` placé **après** le `<style>` existant du site.
- **Inline** : coller le contenu de `themes.css` **à la fin** du `<style>` existant.

L'ordre (après) + la spécificité `:root[data-theme="x"]` garantissent que les valeurs de thème l'emportent sur le `:root` d'origine.

### §3 — Définir le thème par défaut sur `<html>`
```html
<html lang="fr" data-theme="bois">
```
Et ajoutez le **script anti-flash** (§4 de `theme-switcher.html`) tout en haut du `<head>` pour appliquer le thème mémorisé dès le premier rendu (évite un flash du thème par défaut).

### §4 — Insérer le sélecteur
Depuis `theme-switcher.html` :
- **Markup** (4 pastilles colorées) → dans le `<header>`, à droite de la `<nav>`. Adapter le conteneur aux classes réelles.
- **Style du sélecteur** → dans le `<style>` (ou `themes.css`).

> **Sélecteurs à vérifier** dans les ajustements structurels de `themes.css` : ils ciblent `body`, `body::before`, `.clock.running`, `button.primary`, `.board`, `.sq.lastmove`. Confirmez que ces classes existent dans `index.html` (elles correspondent à la refonte bois/or livrée) ; sinon, ajustez les sélecteurs.

### §5 — Brancher le script
Coller le **script** (§3 de `theme-switcher.html`) en bas du `<body>`, **après** le JS du site. Il :
- branche les pastilles (clic → `apply(theme)`),
- pose `data-theme` sur `<html>`,
- mémorise le choix dans `localStorage` sous la clé **`lecercle:theme`** (n'écrase aucune autre clé).

---

## Interactions & comportement
- **Clic sur une pastille** → applique le thème instantanément (les `var()` se propagent), met la pastille en état `.active` (anneau doré), persiste le choix.
- **Au chargement** → lit `localStorage['lecercle:theme']`, applique ce thème (ou `bois` par défaut). Le script anti-flash le fait dès le `<head>`.
- **Validation** → toute valeur inconnue retombe sur `bois`.
- Aucune transition n'est imposée ; pour un fondu, ajoutez `transition: background-color .25s, color .25s` sur les éléments concernés (optionnel).

## État (state)
- Une seule donnée persistée : `localStorage['lecercle:theme']` ∈ `{bois, clair, marbre, nocturne}`.
- Aucun appel réseau, aucune dépendance au backend PHP.

---

## §6 · Tokens de design (contrat complet)
Valeurs exactes par thème dans **`themes.css`**. Variables du contrat (ne pas renommer) :

`--bg --bg-2 --panel --panel-2 --panel-edge --edge-soft --inset --hair --sq-light --sq-dark --frame --gold --gold-bright --gold-deep --check --green --ink --ink-dim --muted --faint --paper --paper-2 --paper-ink --paper-line --font-display --font-text --shadow --shadow-sm`

Rôles clés :
- `--gold*` = **couleur d'accent** du thème (boutons primaires, surbrillances). Vert en Clair, laiton en Marbre, néon en Nocturne.
- `--check` = liseré rouge d'échec (lu par le JS).
- `--sq-light` / `--sq-dark` = cases claires/sombres du plateau.
- `--paper*` = feuille de partie (notation). Devient sombre en Nocturne, clair ailleurs.
- `--font-display` (titres) / `--font-text` (corps).

Une variable méta, `--theme-scheme` (`light`/`dark`), est fournie si vous voulez conditionner du JS (ex. icône lune/soleil).

## Assets
Aucun. Pas d'images : pièces en glyphes Unicode (♔♕♖…), blason = caractère ♞. Polices via Google Fonts.

## Fichiers
- `themes.css` — **les 4 thèmes** (tokens + ajustements structurels). Cœur du livrable.
- `theme-switcher.html` — markup + style + script du sélecteur + anti-flash.
- `reference/Le Cercle - refonte integree.html` — le site réel avec le thème `bois` déjà appliqué (base d'intégration).
- `reference/dir-clair.html`, `reference/dir-marbre.html`, `reference/dir-nocturne.html` — maquettes haute-fidélité de chaque thème (plateau en partie).

## Test de recette
Pour chaque thème : ouvrir le site, cliquer la pastille, vérifier sur **3 vues** (salon, plateau, modale d'accès) que fond, cases, boutons, notation et pendule active sont cohérents. Recharger la page → le thème choisi doit persister. Jouer un coup d'échec → le liseré `--check` doit apparaître à la bonne couleur.
