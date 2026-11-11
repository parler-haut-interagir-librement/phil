<!-- phil-dev source-sha256=4d93e1c9cb8529abc08298b25976d3bbf2c39fe804e88cd97d1fd47292d8f2d1 -->
# phil/footnotes-sidenotes

**Des notes de bas de page et des notes en marge pour les documents UJO (Universal JSON Output), à l'écran et à
l'impression.**

phil/footnotes-sidenotes travaille sur les notes qu'un document UJO contient déjà : les blocs `footnote` et les renvois
`<sup data-footnote="id">` de son texte. Un résolveur parcourt le document une fois et recueille les deux. Il numérote
les notes dans l'ordre de lecture, en recommençant par document, par chapitre ou par marqueur de page, en chiffres
décimaux, en chiffres romains ou en symboles, selon la locale. Il signale ce qui ne va pas sous forme de résultats
typés : un renvoi sans note, une note jamais appelée, un identifiant en double, un renvoi à l'intérieur d'une note, une
note appelée plusieurs fois.

L'appelant choisit ensuite où vont les notes. À la fin du document ou de chaque section, dans une région étiquetée, avec
des paires de liens entre le renvoi et la note. À côté du texte, en notes de marge : une colonne sur les écrans larges,
une bascule dans le texte sur les écrans étroits, en CSS seulement. Au pied de la page imprimée, en CSS GCPM, pour un
moteur d'impression qui place les notes en bas de page.

Il s'adresse à tout texte assez long pour avoir besoin de notes : un livre, un livre de règles de jeu, une page de
documentation. C'est une simple bibliothèque PHP sur `phil/ujo`. Elle ne conserve rien entre deux appels et est fournie
avec deux petites feuilles de style qui habillent du HTML sémantique avec les classes de PHIL UX.

Le texte d'une note passe toujours par l'assainisseur en ligne de `phil/ujo`, jamais brut. Les identifiants portent un
préfixe : plusieurs documents peuvent donc partager une page.

## État

| Partie | État |
|---|---|
| `NoteResolver`, `Resolution`, `Note`, `NoteReference`, `Problem`, `ProblemKind` | Implémenté et testé |
| `NoteOptions`, `Restart`, `Numbering` : décimal, romain, symboles, par locale | Implémenté et testé |
| `Html\NotesRenderer`, `Placement` : fin du document, fin de section, marge, pied de page | Implémenté et testé |
| `NoteLabels` : anglais, français, toute autre langue fournie par l'application | Implémenté et testé |
| `Text\NotesPlainText` : renvois et notes en texte brut | Implémenté et testé |
| `Stylesheet` : les feuilles pour l'écran et l'impression, le compte par page du moteur | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le service des feuilles de style. |
| [Résoudre les notes](doc/fr/resolving.md) | L'ordre de lecture, les redémarrages, les styles de numérotation et les locales, les problèmes signalés. |
| [Emplacements](doc/fr/placements.md) | Les quatre emplacements et le HTML que chacun écrit. |
| [Accessibilité](doc/fr/accessibility.md) | Les paires de liens, les identifiants, les régions, les rôles, les libellés, le clavier et la réduction des animations. |
| [Style](doc/fr/styling.md) | Les classes, les jetons, la colonne de marge et la bascule des écrans étroits. |
| [Impression](doc/fr/print.md) | CSS GCPM, les libellés dans l'appel et le marqueur, les espaces, les redémarrages par page réelle. |
| [Texte brut](doc/fr/plain-text.md) | La forme destinée à un index de recherche ou à une vérification d'accessibilité. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application choisit, complète ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, les bancs d'essai, la cible de fuzzing et le seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/footnotes-sidenotes
```

```php
use Phil\FootnotesSidenotes\Html\NotesRenderer;
use Phil\FootnotesSidenotes\NoteLabels;
use Phil\FootnotesSidenotes\NoteOptions;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Numbering;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\ProblemKind;
use Phil\FootnotesSidenotes\Restart;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Ujo;

$document = new Ujo()->decode($json);

$resolver = new NoteResolver(new NoteOptions(
    restart: Restart::Chapter,
    locales: ['fr' => Numbering::symbols()],
));
$notes = $resolver->resolve($document, 'fr-CA');

foreach ($notes->problemsOf(ProblemKind::MissingNote) as $problem) {
    // $problem->noteId, $problem->blockId
}

$renderer = new NotesRenderer(HtmlRenderer::default(), NoteLabels::forLocale('fr-CA'));

echo $renderer->render($document, $notes, Placement::Margin, idPrefix: 'chapter-3');
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-hash` et `ext-mbstring`, et `phil/ujo`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
