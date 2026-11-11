<!-- phil-dev source-sha256=c440964b03327fba27003cda50cca00e08ca11781b1340ed15229b5371e80118 -->
# Affichage

## Le gabarit

`@PhilContentWarnings/fold.html.twig` enveloppe un contenu dans ses avertissements, selon ce que décident les
préférences du lecteur courant :

```twig
{% embed '@PhilContentWarnings/fold.html.twig' with {warnings: book.warnings, id: 'book-' ~ book.id} %}
    {% block content %}{{ book.excerpt }}{% endblock %}
{% endembed %}
```

| Décision | Ce qui est rendu |
|---|---|
| Aucun avertissement | Le contenu seul |
| Affiché | Une note qui nomme les avertissements avec leur sévérité, les notes, puis le contenu ; `notice: false` produit le rendu du contenu seul |
| Replié | La même note et les mêmes notes, puis un élément `details` dont le `summary` révèle le contenu |
| Masqué | Une courte note qui nomme les thèmes qui ont masqué le contenu ; `hidden_notice: false` ne produit aucun rendu |

| Variable | Signification |
|---|---|
| `warnings` | Un `ContentWarnings`, ou null |
| `id` | L'`id` de l'élément du contenu |
| `notice` | `false` laisse de côté les avertissements d'un contenu affiché |
| `hidden_notice` | `false` ne produit aucun rendu pour un contenu masqué |

Un contenu masqué n'est jamais envoyé au navigateur. Les messages sont dans le domaine `phil_content_warnings`.

## Fonctions Twig

| Fonction | Donne |
|---|---|
| `content_warnings_decision(warnings)` | La `Decision` pour le lecteur courant |
| `content_warning_label(theme, locale = null)` | Le libellé d'un thème, sa clé pour un thème que le catalogue n'a pas |
| `content_warning_description(theme, locale = null)` | La description d'un thème, vide s'il n'en a pas |
| `content_warning_category(theme, locale = null)` | Le libellé de la catégorie du thème, vide pour un thème que le catalogue n'a pas |

Une application produit son propre balisage à partir de la décision lorsque le gabarit ne convient pas.

## Le contrôleur Stimulus

`phil--content-warnings-bundle--reveal`, déclaré dans `assets/package.json`, se trouve sur l'élément d'un contenu
replié. L'élément `details` se charge du repli, au clavier et sans script. Le contrôleur :

| Membre | Ce qu'il fait |
|---|---|
| `toggle` (sur le `toggle` de `details`) | Répartit `revealed` ou `concealed` |
| `reveal` | Ouvre le contenu, lui donne le focus et le fait défiler jusqu'à l'écran : immédiatement lorsque le lecteur demande moins d'animation, en douceur sinon |
| `conceal` | Replie de nouveau le contenu |

Un bouton « tout afficher » de l'application appelle `reveal` sur chaque contrôleur, ou écoute `revealed` pour mémoriser
un choix.

## La feuille de style et l'accessibilité

`assets/styles/content-warnings.css`, importée avec le contrôleur, encadre l'avertissement, fait passer le résumé de
« Show the content » à « Hide the content », donne au contenu qui a le focus un contour visible, et le fait apparaître
en fondu seulement pour les lecteurs qui n'ont pas demandé moins d'animation (`prefers-reduced-motion: no-preference`).

Les avertissements forment un paragraphe `role="note"` lu avant le contenu ; le `summary` est un bouton natif annoncé
comme développé ou réduit ; le contenu prend le focus seulement lorsque `reveal` l'ouvre.
