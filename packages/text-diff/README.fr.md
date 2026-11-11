<!-- phil-dev source-sha256=1805092f9231ddb7c0aa30d33d27e942f10522b33f6b53cace3b5210cfcf9464 -->
# phil/text-diff

**Ce qui a changé entre deux textes, mot par mot, paragraphe par paragraphe ou bloc par bloc — affiché en HTML ou
énuméré.**

phil/text-diff compare deux textes et donne le plus court ensemble de suppressions et d'insertions qui transforme
l'ancien en nouveau. Il découpe les textes en mots, en lignes ou en paragraphes. Il lit le HTML sans jamais couper une
balise : la différence de deux pages est elle-même une page bien formée. Il compare aussi deux documents en UJO
(Universal JSON Output) — le format de documents par blocs que lit `phil/ujo` — bloc par bloc, par identifiant de bloc,
puis champ par champ à l'intérieur de chaque bloc modifié. Chaque différence sort en HTML avec `<ins>` et `<del>`, ou
sous forme de liste structurée de changements avec leurs positions.

Il s'adresse à toute application PHP qui conserve des versions de ce que les gens écrivent et doit montrer ce qui a
bougé entre deux d'entre elles : l'historique des révisions d'un article, d'une page ou d'un wiki, la relecture d'une
modification proposée, les segments d'une traduction dont la source a changé, la vérification d'un document avant sa
nouvelle publication. `phil/content-bundle` conserve les versions de ses contenus ; cette bibliothèque permet à une
application d'en montrer les différences.

C'est une simple bibliothèque PHP dont les seuls prérequis sont `ext-mbstring` et `phil/ujo`. La
comparaison suit l'algorithme de différence en O(ND) d'Eugene W. Myers, exécuté après avoir mis de côté le début et la
fin communs des deux textes. Elle est bornée : au-delà d'un certain nombre de modifications — mille par défaut — le
reste est affiché comme remplacé en entier, ce qui limite le temps et la mémoire sur des textes réécrits d'un bout à
l'autre. La sortie est déterministe.

La conception est sobre : un `Tokenizer` découpe un texte en jetons qui le redonnent octet pour octet ; `Myers` trouve
le script ; un `Diff` contient ses `Operation` et énumère ses `Change` ; `HtmlFormatter` écrit le HTML. Pour les
documents, `DocumentDiffer` apparie les blocs de chaque niveau par identifiant en `BlockChange` avec leurs
`FieldChange`, rassemblés dans un `DocumentDiff` dont `DocumentFormatter` produit le rendu par `phil/ujo`. `TextDiff`
réunit le tout.

## État

| Partie | État |
|---|---|
| Algorithme de Myers, avec début et fin communs mis de côté et une borne de modifications | Implémenté et testé |
| Mots, lignes et paragraphes ; HTML avec balises, commentaires et références de caractères entiers | Implémenté et testé |
| Écritures sans espaces (han, kana, thaï, lao, khmer, birman) comparées caractère par caractère | Implémenté et testé |
| Espaces entre deux changements joints à ceux-ci ; suppressions avant insertions dans chaque changement | Implémenté et testé |
| HTML d'une différence : texte brut échappé, HTML qui garde la structure du nouveau texte | Implémenté et testé |
| Liste de changements : insertions, suppressions et remplacements avec leurs positions en points de code | Implémenté et testé |
| Documents UJO : blocs appariés par identifiant à chaque niveau, conservés, modifiés, déplacés, ajoutés ou retirés ; valeurs comparées par chemin | Implémenté et testé |
| HTML de la différence de deux documents, chaque bloc rendu par `phil/ujo` et marqué | Implémenté et testé |
| Listes de valeurs autres que des blocs, alignées par contenu plutôt que par position | Pas commencé : elles sont comparées par indice |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Comparer des textes](doc/fr/texts.md) | `TextDiff`, les découpeurs, les opérations, les changements et la borne de modifications. |
| [Sortie HTML](doc/fr/html.md) | `HtmlFormatter` : texte brut et HTML, ce qui est marqué et ce qui est conservé, la mise en forme. |
| [Comparer des documents](doc/fr/documents.md) | Les documents UJO bloc par bloc : appariement, déplacements, chemins des champs, blocs imbriqués, HTML. |
| [Versions des contenus](doc/fr/versions.md) | Montrer les différences entre deux versions conservées par `phil/content-bundle`. |
| [Exceptions](doc/fr/exceptions.md) | `TextDiffException` et ses deux exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests et le seuil de mutation de la bibliothèque, et comment tester du code qui montre des différences. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/text-diff
```

```php
use Phil\TextDiff\TextDiff;

$differ = new TextDiff();

echo $differ->toHtml($differ->words('The quick brown fox', 'The slow brown dog'));
// The <del>quick</del><ins>slow</ins> brown <del>fox</del><ins>dog</ins>

echo $differ->toHtml($differ->html('<p>Hello <b>big</b> world</p>', '<p>Hello <i>small</i> world!</p>'));
// <p>Hello <del>big</del><i><ins>small</ins></i> world<ins>!</ins></p>

foreach ($differ->paragraphs($before, $after)->changes() as $change) {
    printf("%s at %d: %s -> %s\n", $change->kind->value, $change->oldOffset, $change->old, $change->new);
}
```

```php
use Phil\Ujo\Ujo;

$ujo = new Ujo();
$diff = $differ->documents($ujo->decode($oldJson), $ujo->decode($newJson));

echo $differ->documentToHtml($diff);
```

## Prérequis

PHP 8.5 ou plus récent, `ext-mbstring` et `phil/ujo`.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
