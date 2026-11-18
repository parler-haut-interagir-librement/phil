<!-- phil-dev source-sha256=472b1d28c9b39acb902cad25b020a552bd6ce48b94dc0bf5c88e9297fe9d6cee -->
# Installation

## Stabilité

Les membres sont des versions alpha, publiées sous la forme `0.x.y-alpha`. Composer les installe dans un projet qui
accepte cette stabilité ; `prefer-stable` garde tous les autres paquets à leur version stable quand il y en a une :

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-editorial
```

Les membres exigent Symfony 8.2 ou une version plus récente : tant que la 8.2 n'a pas de version stable, les mêmes
réglages permettent à Composer d'installer sa bêta ou sa version candidate.

Un indicateur de stabilité sur la seule exigence, `phil/profile-editorial:@alpha`, ne suffit pas : Composer applique
les indicateurs aux paquets que le projet nomme, jamais aux membres qu'un métapaquet exige.

## Enregistrer les bundles

Avec Symfony Flex, chaque bundle est ajouté à `config/bundles.php` à son installation. Sans Flex, ajoutez-les :

```php
// config/bundles.php
return [
    // …
    Phil\MarkupBundle\PhilMarkupBundle::class => ['all' => true],
    Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
    Phil\StructuredDataBundle\PhilStructuredDataBundle::class => ['all' => true],
];
```

`phil/citations` et `phil/footnotes-sidenotes` sont des bibliothèques : l'application construit leurs services là où
elle en a besoin, sans bundle.

## Ce qu'apporte l'application

| Paquet | Pourquoi |
|---|---|
| `symfony/twig-bundle` | Les filtres `markup_html`, `markup_purify` et `markup_ujo`, la fonction `structured_data()`, et le gabarit de repli des avertissements de contenu. |
| `symfony/form` | Les champs `MarkupFormatType`, `ContentWarningsType` et `WarningPreferencesType`. |
| `doctrine/dbal` | Le type de colonne `phil_content_warnings`, quand les avertissements sont stockés avec une entité. |
| StimulusBundle avec AssetMapper | Le contrôleur `reveal` qui déplie un contenu avec avertissement. |

## Ce qu'il faut configurer ensuite

Chaque membre fonctionne avec ses valeurs par défaut une fois enregistré. Les étapes habituelles :

1. Choisissez dans `phil_markup` les formats et les niveaux de purification que les auteurs peuvent employer, et
   produisez le rendu de leur texte avec `markup_html`.
2. Décrivez le site et son propriétaire dans `phil_structured_data`, et imprimez `{{ structured_data() }}` à la fin du
   gabarit de base.
3. Ajoutez une colonne `phil_content_warnings` au contenu qui a besoin d'avertissements, et affichez-le avec
   `@PhilContentWarnings/fold.html.twig`.
4. Résolvez les notes d'un document UJO avec `NoteResolver`, et ses références avec `CitationProcessor`.

La page des [membres](members.md) renvoie à la documentation de chacun.
