<!-- phil-dev source-sha256=ba06e02f31a81186e2a91b2bb566760b709cddbc72caff7a7e951efa8ca16887 -->
# Export PDF

Une présentation imprimée compte une page par diapositive, tous les fragments montrés. Les notes sont omises sauf
demande ; avec elles, chaque diapositive est imprimée sur une page verticale, la diapositive au-dessus de ses notes.

## Le PDF

Avec `phil/paged-media-bundle` installé et un moteur d'impression configuré, `DeckPdf` produit le rendu d'une
présentation par `PagedMedia::printHtml()` :

```php
use Phil\Slides\Infrastructure\Print\DeckPdf;

$output = $deckPdf->render($deck);                                // the slides only
$output = $deckPdf->render($deck, withNotes: true, filename: 'talk-notes.pdf');
$output = $deckPdf->render($deck, pipeline: new Pipeline(…));     // then PDF/A, a signature… through Gotenberg

return new Response($output->contents, 200, ['Content-Type' => $output->contentType]);
```

L'impression s'annonce comme toute impression de `phil/paged-media-bundle`, et les étapes du pipeline s'exécutent
après elle. Sans ce bundle, le service `DeckPdf` est retiré.

Le PDF avec les notes est aussi servi, au rôle `phil_slides.presenter_role` seulement, par la route du présentateur
`phil_slides_presenter_notes_pdf` (voir [Vue du présentateur](presenter.md)) ; la vue du présentateur y renvoie par un
lien.

## La page pour n'importe quel moteur

`DeckPrint`, enregistré avec TwigBundle, donne ce qu'un moteur met en page :

| Méthode | Donne |
|---|---|
| `body($deck, $withNotes)` | Le corps, tiré de `@PhilSlides/print.html.twig`, chaque diapositive rendue par `phil/ujo-bundle` |
| `stylesheets($deck, $withNotes)` | La règle `@page` écrite par `phil/paged-media`, puis `assets/styles/slides-print.css` |
| `document($deck, $withNotes)` | Le document HTML complet, son titre et sa langue tirés des directives |
| `language($deck)` | La langue de la présentation, `en` quand sa source n'en nomme aucune |

| Pages | Taille |
|---|---|
| Diapositives, 16:9 | 960 × 540 points, sans marge |
| Diapositives, 4:3 | 720 × 540 points, sans marge |
| Avec les notes | US Letter ou A4 à la verticale, selon `pdf.notes_page_size`, marges d'un demi-pouce |

## Imprimer depuis le navigateur

La feuille de style de la page de l'auditoire imprime elle aussi une diapositive par page, tous les fragments montrés,
sans les commandes ; la vue du présentateur imprime la même chose, sans ses notes.
