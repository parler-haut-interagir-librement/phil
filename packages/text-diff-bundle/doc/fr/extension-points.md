<!-- phil-dev source-sha256=4a14667ff1d937845b1b91e5b22faafe9d5ad7e1657472f2d21a3c76c7e7dd54 -->
# Points d'extension

Le bundle câble la bibliothèque ; ce qui est propre à l'application reste dans ses propres services.

## Les services UJO

`text_diff_document` lit les documents avec le service `Phil\Ujo\Ujo` et produit le rendu de leurs blocs avec le
service `Phil\Ujo\Html\HtmlRenderer` quand le conteneur les contient — `phil/ujo-bundle` enregistre les deux, avec les
types de blocs de l'application, et d'autres bundles décorent le moteur de rendu, comme le fait
`phil/highlight-bundle` pour colorer les blocs de code. Un type de bloc propre à l'application est rendu dans une
différence comme il l'est sur la page.

## L'assainisseur HTML

`html_sanitizer` nomme n'importe quel service qui implémente `Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface` :
l'un des assainisseurs de FrameworkBundle, ou celui de l'application. Il décide quel HTML une comparaison par `html`
conserve des valeurs qu'un gabarit ne marque pas comme sûres ([Twig](twig.md)).

## Le comparateur

`Phil\TextDiff\TextDiff` est un service public, câblé automatiquement par sa classe : un contrôleur ou un service
compare des textes avec lui et énumère les changements — `Diff::changes()` — pour les montrer à sa façon, ou compare
des documents avec `documents()`.

## Les libellés et les touches du contrôleur

Le contrôleur `comparison` tire ses libellés de ses valeurs et laisse les raccourcis clavier à la page
([JavaScript](javascript.md)) ; les couleurs de la feuille de style sont des propriétés personnalisées
([Mise en forme](styling.md)).
