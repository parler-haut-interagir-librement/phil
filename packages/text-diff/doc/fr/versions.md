<!-- phil-dev source-sha256=e0b4d1930f74ed68ca66847c4d3882aa6a667aedd7d538742407696311261791 -->
# Versions des contenus

`phil/content-bundle` conserve les versions de ses contenus : une version publiée est figée, la modification suivante
crée une nouvelle version, et un corps publié garde sa source UJO avec son HTML compilé (`Body`, format `ujo`). Le
bundle conserve et publie les versions mais n'en affiche aucune : il n'a donc aucun écran propre où brancher une
différence. Une application qui affiche l'historique d'un contenu compare deux versions avec cette bibliothèque.

## Deux versions publiées

Les deux corps sont en UJO : comparez-les comme des documents.

```php
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Ujo;

function bodyDifference(BodyCapable $old, BodyCapable $new, TextDiff $differ, Ujo $ujo): string
{
    $before = $old->getBody();
    $after = $new->getBody();

    if (null === $before || null === $after) {
        return '';
    }

    return $differ->documentToHtml($differ->documents($ujo->decode($before->source), $ujo->decode($after->source)));
}
```

Passez le `HtmlRenderer` de l'application — celui que configure `phil/ujo-bundle` — comme deuxième argument de
`documentToHtml()`, pour que la différence soit rendue comme la page.

## Un brouillon et une version publiée

Un brouillon garde le format de son auteur : Markdown, BBCode, une syntaxe de wiki ou UJO. Il y a deux façons de le
comparer avec la version publiée :

- **tel que le lecteur le verra** : convertissez le brouillon en UJO avec le convertisseur de `phil/markup-bundle` (ou
  le `BodyConverter` du bundle de contenu), puis comparez les documents comme ci-dessus. Les lecteurs des formats
  écrits numérotent les blocs dans l'ordre de lecture (`b1`, `b2`…) : un bloc inséré près du début renumérote donc
  chaque bloc qui le suit, et les blocs sont alors appariés par position plutôt que par identité. Pour de tels textes,
  le HTML rendu se compare mieux : `$differ->toHtml($differ->html($before->html, $after->html))` ;
- **tel que l'auteur l'a écrit** : quand les deux versions sont dans le même format écrit, comparez leurs sources par
  paragraphes ou par lignes, `$differ->paragraphs($before->source, $after->source)`, et affichez le résultat dans un
  élément `pre-wrap`.

## Autres champs

Les titres, les résumés et les autres textes courts se comparent par mots, `$differ->words()` ; une valeur qui n'est
pas du texte — une date, un indicateur — n'a besoin d'aucune bibliothèque.
