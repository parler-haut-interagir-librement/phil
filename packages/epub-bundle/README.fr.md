<!-- phil-dev source-sha256=854f668c4198b4622f51a650642c4aea7a86ad2184bdd982b3b5459d040a0035 -->
# phil/epub-bundle

**Des livres numériques produits par une application Symfony : construits à partir des enregistrements de l'application,
écrits sur-le-champ ou dans un worker, conservés là où l'application garde ses fichiers, avec les polices qu'elle a le
droit d'incorporer.**

phil/epub-bundle intègre `phil/epub` à une application Symfony. Un seul service, `Epubs`, écrit un livre sous forme de
fichier EPUB 3, en octets ou dans le stockage : un répertoire, ou la médiathèque de `phil/media-bundle`. Des sources de
livres, enregistrées par nom avec un attribut, construisent un livre à partir d'un identifiant. Un message Messenger,
`WriteEpub`, ne transporte donc qu'un nom et un identifiant, et le livre est reconstruit dans le worker ; une fois
conservé, le livre est annoncé par un événement. Une commande écrit un livre à partir d'un terminal. Avec
`phil/fonts-bundle`, `EpubFonts` incorpore des familles du répertoire de polices : il refuse une face dont la licence
interdit l'incorporation, obscurcit les autres et les déclare dans une feuille de style. Avec `phil/media-bundle`,
`MediaAssets` transforme des médias en images, en couverture avec son texte de remplacement, ou en polices.

Il s'adresse à toute application Symfony qui publie de longs textes : récits, livres de règles, manuels, recueils
d'articles.

C'est un bundle bâti par-dessus `symfony/config`, `symfony/dependency-injection`, `symfony/console` et
`symfony/filesystem`. Messenger, un répartiteur d'événements, `phil/fonts-bundle` et `phil/media-bundle` sont
facultatifs : chaque intégration est enregistrée lorsque son paquet est installé.

## État

| Partie | État |
|---|---|
| Configuration : répertoire temporaire, stockage (répertoire ou médiathèque), polices | Implémenté et testé |
| `Epubs`, `BookSource`, `#[AsBookSource]` et `BookSources` | Implémenté et testé |
| `EpubStorage`, `DirectoryStorage`, `MediaLibraryStorage` | Implémenté et testé |
| `WriteEpub`, `EpubHandler` et `EpubStored`, avec nouvelle tentative lorsque l'échec peut être passager | Implémenté et testé |
| `phil:epub:write` | Implémenté et testé |
| `EpubFonts` pour `phil/fonts-bundle`, `MediaAssets` pour `phil/media-bundle` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'activation du bundle. |
| [Configuration](doc/fr/configuration.md) | Chaque option, et les services qu'elle fournit. |
| [Écrire des livres](doc/fr/writing.md) | `Epubs`, les sources de livres, le stockage et la commande. |
| [Messenger](doc/fr/messenger.md) | `WriteEpub`, le handler, l'événement et ce qui fait l'objet d'une nouvelle tentative. |
| [Polices et médias](doc/fr/fonts-and-media.md) | `EpubFonts` et `MediaAssets`. |
| [Points d'extension](doc/fr/extension-points.md) | Les services et les ports sur lesquels une application s'appuie ou qu'elle remplace. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lance le bundle, en plus des exceptions de la bibliothèque. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et le test d'une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/epub-bundle symfony/messenger
```

```php
#[AsBookSource('story')]
final readonly class StoryBooks implements BookSource
{
    public function __construct(private Stories $stories, private EpubFonts $fonts) {}

    public function build(string $id): Book
    {
        $story = $this->stories->get($id);

        return $this->fonts->embed(new Book($story->metadata())->withChapter(...$story->chapters()), 'Example Serif');
    }
}

$bus->dispatch(new WriteEpub('story', '42', 'stories/42.epub'));
```

```sh
bin/console phil:epub:write story 42 --output=var/story-42.epub
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et `phil/epub` ; Symfony Messenger pour le message ; `phil/fonts-bundle` pour
`EpubFonts` ; `phil/media-bundle` pour le stockage dans la médiathèque et `MediaAssets`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
