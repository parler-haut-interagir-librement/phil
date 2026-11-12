<!-- phil-dev source-sha256=d8d4f58a4bb1869e1e2746eebedea1c38367260fb131093308a371858946adf7 -->
# phil/translation-bundle

**Un écran de traduction pour Symfony : des segments qui survivent aux modifications, une mémoire qui se souvient, un
glossaire qui vérifie.**

phil/translation-bundle apporte `phil/translation-memory` dans une application Symfony. Un document écrit en blocs
(un document UJO (Universal JSON Output), le format de document à blocs de `phil/ujo`) est découpé en segments
conservés dans Doctrine, une ligne par segment et par langue cible, avec son état, de `untranslated` à `reviewed`.
Quand l'auteur modifie la source, les segments sont mis à jour : ceux qui n'ont pas changé gardent leur traduction,
ceux qui ont bougé l'emportent avec eux, ceux qui ont changé deviennent `stale`. Les traducteurs travaillent sur un
écran qui montre chaque segment avec ce que propose la mémoire de traduction, les termes et les problèmes du glossaire,
les contrôles de qualité, ce qui a changé sous une cible périmée et ses commentaires. Ils peuvent exporter le document
en XLIFF 2.1 pour un outil de traduction, et réimporter du XLIFF 2.x ou 1.2. Une variante régionale comme `fr-CA` se
lit par son parent : seuls les segments qui diffèrent au Canada sont traduits de nouveau. La traduction automatique
propose des cibles derrière un port, désactivée par défaut et signalée comme telle, et les traducteurs sont remerciés
par un crédit public.

Il s'adresse aux applications Symfony qui publient en plus d'une langue ce que les gens écrivent : articles, pages,
règles, livres. La mémoire et les glossaires sont partagés par tous les documents de l'application. Une phrase
traduite une fois est donc proposée partout, et un terme est traduit de la même façon d'un bout à l'autre. Il ne
traduit que des documents UJO : les catalogues de messages de l'interface d'une application restent au composant de
traduction de Symfony et à ses outils.

C'est un bundle bâti sur la bibliothèque. Il exige Doctrine ORM, Twig et les composants HTTP et de routage de
Symfony. L'application garde les documents : elle les nomme au bundle par un service `SourceDocuments`, dit qui peut
traduire et réviser par une `TranslationPolicy`, et publie le document traduit que le bundle assemble. Tant que ces
deux services ne sont pas configurés, l'écran répond 404.

La conception suit les couches du monodépôt : `Domain` contient les lignes (`SegmentRecord`, `MemoryRecord`,
`TermRecord`, `CommentRecord`) et les ports de leurs stockages ; `Application` contient `Translations`, `Glossaries`,
`Comments`, `Progress` et les ports de l'application (`SourceDocuments`, `TranslationPolicy`, `Translators`,
`MachineTranslator`) ; `Infrastructure` contient les stockages Doctrine et en mémoire, les contrôleurs et leurs routes,
la fonction Twig des crédits, les liens EasyAdmin et le branchement des services.

## État

| Partie | État |
|---|---|
| Segments dans Doctrine, préparés à partir d'un document UJO et mis à jour quand la source change | Implémenté et testé |
| Correspondances exactes de la mémoire proposées aux nouveaux segments | Implémenté et testé |
| Traduire, proposer, réviser, confirmer et vider un segment ; cibles utilisables apprises par la mémoire | Implémenté et testé |
| Mémoire de traduction et glossaires dans Doctrine, partagés par tous les documents | Implémenté et testé |
| Exportation XLIFF 2.1, importation XLIFF 2.x et 1.2 avec un rapport | Implémenté et testé |
| Le document traduit assemblé à partir des segments utilisables | Implémenté et testé |
| Écran de traduction en Twig : segments, correspondances, termes, problèmes, changements, formulaires, exportation et importation | Implémenté et testé |
| Variantes régionales lues par leur parent, `fr-CA` et `en-CA` par défaut ; lectures traduite et brute | Implémenté et testé |
| Contrôles de qualité à l'écran, écartés par segment grâce à un indicateur conservé avec sa ligne | Implémenté et testé |
| Une cible par segment et des commentaires sur les segments, signés par l'utilisateur courant | Implémenté et testé |
| Qui a écrit et révisé chaque cible ; un crédit public des traducteurs, en Twig | Implémenté et testé |
| Pages de glossaire par `Glossaries`, formes fléchies, exportation et importation TBX, liens EasyAdmin | Implémenté et testé |
| Traduction automatique derrière un port à adaptateurs nommés, désactivée par défaut, signalée comme produite par une machine | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, les tables et les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_translation`. |
| [Traduire des documents](doc/fr/translations.md) | `Translations` : la préparation, les actions sur un segment, l'avancement, le XLIFF, le document traduit. |
| [L'écran de traduction](doc/fr/screen.md) | Les routes, ce que montre la page, les formulaires, les services de l'application, la mise en page. |
| [Mémoire et glossaires](doc/fr/memory-and-glossaries.md) | La mémoire partagée, `Glossaries`, et la façon dont les deux atteignent l'écran. |
| [Variantes régionales](doc/fr/variants.md) | La chaîne de `fr-CA` à `fr` puis à la source, les lectures traduite et brute. |
| [Commentaires et crédits](doc/fr/collaboration.md) | Une cible par segment, les commentaires, qui a fait quoi, le crédit public, `Translators`. |
| [Pages de glossaire](doc/fr/glossary-pages.md) | La modification des glossaires à l'écran, le TBX, les liens EasyAdmin, les formes fléchies. |
| [Traduction automatique](doc/fr/machine-translation.md) | Le port, les adaptateurs, les propositions signalées comme produites par une machine. |
| [Stockage](doc/fr/storage.md) | Les trois tables, leurs clés et les stockages en mémoire. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lance le bundle, et ce que répond l'écran. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/translation-bundle
```

```yaml
# config/packages/phil_translation.yaml
phil_translation:
    granularity: sentence
    sources: App\Translation\ArticleSources      # implements SourceDocuments
    policy: App\Translation\EditorsPolicy        # implements TranslationPolicy

# config/routes/phil_translation.yaml
phil_translation:
    resource: '@PhilTranslationBundle/config/routes.php'
    prefix: /admin/translate
```

L'écran d'un article est alors `/admin/translate/article:42/fr`, et sa version française est assemblée par :

```php
$french = $translations->translated('article:42', $article->content(), LanguageTag::of('fr'));
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine ORM 3.7, Twig 3.21, `phil/translation-memory` et `phil/ujo`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
