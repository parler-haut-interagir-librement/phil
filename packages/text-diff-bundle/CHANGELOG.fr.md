<!-- phil-dev source-sha256=10eeb6adccaab68b390f574f2075daf42c69507332d569b31153ab3d854792c9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/text-diff-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilTextDiffBundle` et la configuration `phil_text_diff` : `max_edits`, la borne des modifications d'une comparaison
  ; `tokenizer`, ce par quoi compare le filtre `text_diff` quand le gabarit ne nomme rien ; et `html_sanitizer`,
  l'assainisseur du HTML qu'un gabarit ne marque pas sûr.
- `TextDiffServices` : le service public `TextDiff`, qui vérifie les blocs d'une différence de documents avec le service
  `Normalizer` de `phil/ujo-bundle` quand il est installé, et l'extension Twig, qui lit les documents avec le service
  `Ujo` et les rend avec son service `HtmlRenderer`.
- Les filtres Twig `text_diff`, deux textes par mots, lignes, paragraphes ou HTML, le texte brut échappé et le HTML jugé
  fiable seulement comme `Twig\Markup`, assaini ou échappé sinon ; et `text_diff_document`, deux documents UJO, ou leur
  JSON, bloc par bloc.
- Le contrôleur Stimulus `comparison`, `assets/src/comparison_controller.js` et `assets/src/lib/marks.js` : les vues en
  ligne, ancienne et nouvelle écrites comme `data-text-diff-mode`, les boutons de mode enfoncés, le changement suivant
  et précédent marqué, mis au focus et amené en vue, un remplacement comme un seul changement, et un compteur en direct
  ; associés pour AssetMapper comme `@phil/text-diff-bundle`.
- `assets/styles/text-diff.css` : les insertions, les suppressions et les blocs d'une différence de documents, les vues
  et le changement courant, clair et sombre selon le système ou `data-theme`, clair sur papier, ses couleurs des
  propriétés personnalisées qui respectent le contraste WCAG AA, associé pour AssetMapper comme
  `phil-text-diff/text-diff.css`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
