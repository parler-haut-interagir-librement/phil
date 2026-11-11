<!-- phil-dev source-sha256=f7849b6d9984393b5e0ea02f1bf485e196f5181b1b5df9c15ff0cd81360c3d2f -->
# phil/taxonomy-bundle

**Des étiquettes et un arbre de catégories traduit pour toute sorte de ressource, avec des types de formulaire pour
les modifier.**

phil/taxonomy-bundle donne à une application Symfony des étiquettes et un arbre de catégories. Une étiquette est
trouvée ou créée par son nom, quels que soient sa casse ou ses accents, puis renommée ou fusionnée dans une autre. Une
catégorie a une clé, un parent, une position parmi ses sœurs, et des noms et descriptions par langue. Elle se déplace
avec toute sa branche, jamais sous elle-même, et se fusionne dans une autre. Une ressource est étiquetée et classée par
sa sorte et son identifiant, puis retrouvée par étiquette ou par branche entière d'un seul coup.

Il s'adresse à une application dont les rédacteurs classent des pages, des événements ou des produits, et dont les
visiteurs les parcourent par sujet dans leur propre langue. Deux noms qui ne diffèrent que par les accents ou la casse
forment une seule étiquette : `Jeux de rôle` et `jeux de role` ne coupent donc jamais une liste en deux. La fusion
élimine les doublons sans perdre une seule attribution.

Le bundle conserve ses tables avec Doctrine ORM et désigne les ressources par `phil/resource` : une étiquette n'a donc
jamais besoin d'une clé étrangère vers l'entité étiquetée. Les *slugs* et les clés de catégorie viennent de
`phil/slug`, ou d'un générateur de *slugs* nommé de `phil/slug-bundle`. `TagsType` et `CategoryType` se branchent sur
Symfony Form : les étiquettes saisies en texte séparé par des virgules, et une catégorie choisie dans l'arbre en
retrait. Chaque catégorie garde son chemin depuis la racine, ce qui permet de trouver une branche entière en une seule
requête.

## État

| Partie | État |
|---|---|
| `Tag`, `Category`, `CategoryTranslation`, `Assignment` ; le port du dépôt | Implémenté et testé |
| `Taxonomy` : étiquettes, catégories, arbre, attributions, fusions | Implémenté et testé |
| `DoctrineTaxonomyRepository` | Implémenté et testé |
| `TagsType`, `CategoryType` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la configuration, les tables. |
| [Étiquettes](doc/fr/tags.md) | Trouver, attribuer, renommer et fusionner des étiquettes. |
| [Catégories](doc/fr/categories.md) | L'arbre, les noms par langue, le déplacement, la fusion, le classement. |
| [Formulaires](doc/fr/forms.md) | `TagsType` et `CategoryType`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
$page = ResourceReference::of('page', $page->id);
$taxonomy->setTags($page, ['Jeux', 'soirée']);

$games = $taxonomy->createCategory('games', null, ['fr' => 'Jeux', 'en' => 'Games']);
$taxonomy->categorize($page, [$games]);
$taxonomy->categorizedIn($games);   // the whole branch
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl` et `ext-mbstring` ; Doctrine ORM 3.7 ; l'injection de dépendances, la
configuration, les formulaires, l'OptionsResolver et l'UID de Symfony 8.2 ; `phil/slug` 0.1 pour les *slugs* et
`phil/resource` 0.1 pour les références de ressources.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
