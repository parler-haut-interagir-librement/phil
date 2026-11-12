<!-- phil-dev source-sha256=23472356ea74e8930f0886a53caac0273e90ed35e4f0694e8e526b5eaaa16a9e -->
# phil/content-bundle

**Des pages et des articles enregistrés par locale, par étape et par version, et publiés du brouillon à la version en
ligne.**

phil/content-bundle enregistre le contenu d'un site Symfony avec Doctrine. Un contenu est une **racine** et ses
**lignes de dimension** : une ligne par locale, par étape (`draft` ou `live`) et par version, plus une **ligne non
localisée** pour les valeurs que partagent toutes les locales. Sur ce stockage, le bundle publie les brouillons vers
l'étape en ligne, fige ce qu'il a publié et crée la version suivante pour la prochaine modification. Il tient une table
de routes qui répond aux anciens slugs par des redirections permanentes. Il sert le contenu en ligne par un contrôleur
qui produit le rendu des gabarits Twig de l'application, donne ses pages au plan du site, écrit des flux Atom et signe
des liens d'aperçu vers les brouillons. Le texte d'un brouillon garde le balisage dans lequel son auteur écrit —
Markdown, BBCode, MediaWiki, DokuWiki, Creole, HTML, texte brut, UJO ou un format qu'apporte un module complémentaire —;
la publication le convertit en UJO (Universal JSON Output), le format de document par blocs de `phil/ujo`, enregistré
avec son HTML sur la ligne en ligne. Le contenu s'écrit depuis une administration reliée à un tableau de bord
EasyAdmin, depuis la console, depuis PHP ou depuis une migration Doctrine, par `ContentManager`. Une publication qui
ferait perdre une partie du texte de l'auteur est refusée tant que le rédacteur ne l'a pas acceptée. Chaque page qui
passe en ligne ou hors ligne répartit `PagePublished` ou `PageRetired` de `phil/publishing`, que le plan du site, un
cache ou un signal peuvent suivre. Les modifications d'un auteur s'enregistrent d'elles-mêmes, en privé, à côté du
brouillon, et des brouillons nommés gardent des variantes. Un genre peut n'avoir qu'un seul contenu — la page
d'accueil —, ou garder lisible chaque publication, datée — une page « maintenant ». Les contenus publiés alimentent un
index de recherche de `phil/search-bundle`, et `phil/scheduled-transitions-bundle` les publie à un moment fixé.

**Ce qu'il ne fait pas** : aucune médiathèque (une image d'extrait est une référence que l'application résout), aucun
modèle d'utilisateur, d'étiquette, de catégorie ou de sécurité, aucun aperçu en direct ni lien de partage privé, aucun
moteur de recherche propre, et aucune chaîne de repli qu'il déduirait seul. L'application les déclare tous.

Il convient à une application Symfony dont les rédacteurs écrivent des pages, des nouvelles ou d'autres genres de
contenu, souvent en plusieurs langues, et dont les lecteurs ne doivent voir que ce qui a été publié. Un site de
quelques pages fixes dans une seule langue, sans brouillons, est mieux servi par de simples routes et des gabarits
Twig. phil/directory-bundle s'appuie sur lui et enregistre chaque fiche comme un contenu. La publication à une heure
fixée passe par le workflow `phil_content_publication`, que `phil/scheduled-transitions-bundle` applique quand le noyau
l'a ; aucun des deux bundles n'exige l'autre.

C'est un bundle Symfony qui se branche sur Doctrine ORM, `symfony/workflow`, le routeur et Twig, et qui utilise
phil/markup, phil/ujo, phil/slug, phil/sitemap, phil/url et phil/publishing. Les capacités (un gabarit, un workflow
éditorial, le référencement, un extrait, un lien, un slug, un corps de texte) sont des paires d'interface et de trait
qu'une classe de ligne compose. Le bundle associe leurs colonnes à chaque classe de ligne qui les implémente, et un
pipeline étiqueté de mappeurs de données, de fusionneurs et de normaliseurs les lit et les écrit. La conception suit le
stockage de contenu de [Sulu](https://github.com/sulu/sulu) 3 (`packages/content`) comme motif seulement, autour de
simples types du domaine.

## État

| Partie | État |
|---|---|
| Dimensions, chaînes de repli, agrégation | Fait, testé |
| Capacités : gabarit, workflow, référencement, extrait, lien, slug, corps purifié | Fait, testé ; chacune avec son mappeur de données (aucun pour le workflow), son fusionneur, son normaliseur et son mappage |
| Cycle de vie du corps : un brouillon dans le format de son auteur, publié en UJO avec son HTML | Fait, testé pour chaque format sur des ports en mémoire et dans un noyau sur SQLite |
| Stockage Doctrine : classes de base, écouteur de mappage, types DBAL, dépôts, transaction | Fait, testé sur SQLite, MySQL 8 et PostgreSQL |
| Publication du brouillon vers la version en ligne sur `symfony/workflow` | Fait, testé sur des ports en mémoire avec plusieurs locales, et dans un noyau sur SQLite, MySQL 8 et PostgreSQL |
| Versions : gel à la publication, création de la version suivante, dernière version | Fait, testé |
| Table de routes, historique des slugs et redirections 301, résolveur | Fait, testé sur SQLite, MySQL 8 et PostgreSQL |
| Contrôleur et rendu Twig (gabarit par clé, contenu, balises de référencement, URL) | Fait, testé dans un noyau |
| Commandes de console : créer, modifier, publier, dépublier, lister | Fait, testé dans un noyau |
| Interface PHP pour les migrations (`ContentManager`), suppression d'une racine | Fait, testé |
| Pages du plan du site, flux Atom, liens d'aperçu signés des brouillons | Fait, testé sur des ports en mémoire et dans un noyau |
| `PagePublished` et `PageRetired` de `phil/publishing` à la publication, à la dépublication et à la suppression | Fait, testé sur des ports en mémoire et dans un noyau |
| Une publication qui perd du contenu refusée, depuis PHP, la console, le workflow et l'administration | Fait, testé |
| Genres uniques (page d'accueil, coordonnées) et archive datée (page « maintenant ») | Fait, testé sur des ports en mémoire et dans un noyau |
| Copies de travail : l'enregistrement automatique privé de chaque auteur, brouillons nommés | Fait, testé dans un noyau sur SQLite |
| Écrans d'administration par les services du bundle, reliés à EasyAdmin | Fait, testé dans un noyau |
| Alimentation de recherche de `phil/search-bundle` (passages UJO par langue) | Fait, testé dans un noyau sur son adaptateur `dbal` |
| Publication planifiée sur `phil/scheduled-transitions-bundle` | Fait, testé sur son `Scheduler` |

## Documentation

| Sujet | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, les bundles du framework qui activent le stockage Doctrine et la couche de rendu, l'enregistrement du bundle et le schéma à migrer. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_content` — `locales`, `roots`, `entity_manager`, `routes`, `templates`, `default_template`, `preview`, `feed_limit`, `raw_html_role`, `singles`, `archives`, `search_index`, `admin` — avec types, valeurs par défaut, contraintes, un exemple annoté, et les services que chacune branche. |
| [Racines, dimensions et chaînes de repli](doc/fr/dimensions.md) | `DimensionKey` et `Stage`, avec `Locale`, `Version`, `FallbackChain` et `DeclaredLocales` de `phil/locale-dimensions` : le modèle, ses invariants, et la lecture d'une dimension. |
| [Stockage Doctrine](doc/fr/storage.md) | Les deux entités qu'écrit une application, les classes de base, l'écouteur de mappage, les types DBAL, les dépôts, les références et les identifiants, et le comportement de la transaction en cas d'échec. |
| [Capacités](doc/fr/capabilities.md) | Les sept capacités, leurs valeurs, colonnes et règles de validation, le chemin de compilation et de purification du corps, sa conversion en UJO à la publication, le HTML brut pour un rôle de confiance, et les adaptateurs de balisage par défaut. |
| [Le pipeline](doc/fr/pipeline.md) | Mappeurs de données, fusionneurs et normaliseurs : les clés des données soumises (« Données soumises »), les règles de fusion, et la forme normalisée que reçoivent les gabarits. |
| [Publication et versions](doc/fr/publication.md) | Les places et transitions du workflow, leur protection, publier et le refus d'une publication qui perd du contenu, les événements de page, dépublier, la planification, l'archive datée, retirer un brouillon, figer et créer des versions, et modifier. |
| [Routage](doc/fr/routing.md) | La table de routes et ses invariants, le montage de `ContentController`, les routes à adresse fixe, ce que répond le contrôleur, et la génération d'URL. |
| [Plan du site, flux et aperçus](doc/fr/sitemap-feeds-previews.md) | Les pages que les contenus donnent au plan du site, avec leurs variantes `hreflang`, le flux Atom d'un genre, et les liens signés vers un brouillon. |
| [Rendu](doc/fr/rendering.md) | La résolution des gabarits, les variables que reçoit un gabarit, les fonctions Twig `content`, `content_single`, `content_archive`, `content_url`, `content_link_url`, `content_seo` et `content_schema`, et la page d'archive. |
| [Administration et copies de travail](doc/fr/administration.md) | L'activation de l'administration, ses écrans et ses boutons, l'entrée de menu d'EasyAdmin, l'enregistrement automatique de chaque auteur, les brouillons nommés et le contrôleur Stimulus. |
| [Recherche](doc/fr/search.md) | Les documents que les contenus publiés donnent à `phil/search-bundle` : leur texte par langue, filtres et identifiants. |
| [Commandes de console](doc/fr/console.md) | `phil:content:create`, `edit`, `publish`, `unpublish` et `list` : arguments, options `--file`, `--field`, `--json` et `--accept-losses`, sortie et codes de sortie. |
| [Écrire du contenu depuis PHP](doc/fr/content-manager.md) | Chaque méthode de `ContentManager`, ses erreurs et ses transactions, et l'écriture de contenu depuis une migration Doctrine. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports de persistance, de balisage et de pipeline avec leurs contrats et des adaptateurs d'exemple, une capacité propre à l'application, les événements du workflow, et chaque identifiant de service, étiquette et nom sur lequel compter. |
| [Tests](doc/fr/testing.md) | Les suites unitaire, fonctionnelle et d'intégration, leurs variables d'environnement, comment les lancer, et comment une application teste du code qui utilise le bundle. |
| [Référence de l'API](doc/fr/reference.md) | Chaque classe et interface par couche, avec son rôle et son état. |

## Prérequis

- PHP 8.5 ou plus récent.
- Les composants Symfony 8.2 (`dependency-injection`, `config`, `workflow`, `routing`, `http-kernel`, `console`,
  …), `doctrine/orm` 3.7, `doctrine/dbal` 4.2, `twig/twig` 3.21, `phil/markup`, `phil/locale-dimensions` et
  `phil/ddd-bundle`.
- Pour le stockage Doctrine et les commandes de console : DoctrineBundle dans le noyau. Pour le contrôleur et les
  fonctions Twig : DoctrineBundle et TwigBundle, avec FrameworkBundle.

La liste complète, avec la raison de chaque prérequis, se trouve dans [doc/fr/installation.md](doc/fr/installation.md).

## Démarrage rapide

Installez le bundle et les bundles du framework qu'il branche. Tant que les paquets ne sont pas sur Packagist,
déclarez un dépôt qui contient `phil/content-bundle` et `phil/ddd-bundle` ; voir
[doc/fr/installation.md](doc/fr/installation.md).

```bash
composer require phil/content-bundle:^0.1@dev phil/ddd-bundle:^0.1@dev doctrine/doctrine-bundle symfony/twig-bundle
```

Enregistrez `Phil\ContentBundle\PhilContentBundle` dans `config/bundles.php`, puis écrivez une racine et sa classe de
ligne :

```php
#[ORM\Entity]
class News extends AbstractContentRoot
{
    #[ORM\Id, ORM\Column(name: 'id', type: NewsIdType::NAME)]
    private NewsId $id;

    public function __construct()
    {
        $this->id = NewsId::generate();
    }

    public static function dimensionContentClass(): string
    {
        return NewsDimension::class;
    }
}

#[ORM\Entity]
class NewsDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable, ExcerptCapable,
    RoutableCapable, BodyCapable
{
    use BodyTrait;
    use ExcerptTrait;
    use RoutableTrait;
    use TemplateTrait;
    use WorkflowTrait;
}
```

La plus petite configuration qui sert des pages :

```yaml
# config/packages/phil_content.yaml
phil_content:
    locales:
        fr: []
    roots:
        - App\Content\News
    routes:
        news: news_show
    default_template: content/news.html.twig

# config/routes/content.yaml
news_show:
    path: /{_locale}/news/{slug}
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController
```

Migrez le schéma (`bin/console doctrine:migrations:diff`, puis `migrate`), puis écrivez et publiez du contenu :

```bash
bin/console phil:content:create news fr --field=slug=opening --field=excerptTitle=Opening \
    --field=bodySource="The site **opens** today." --publish
```

`/fr/news/opening` produit maintenant le rendu de `content/news.html.twig`, qui reçoit le contenu normalisé :

```twig
<h1>{{ content.excerptTitle }}</h1>
{{ content.bodyHtml|raw }}  {# the only value printed unescaped: it is purified on every read #}
```

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

Sulu est lu comme un motif, pour son modèle et ses tests.
