<!-- phil-dev source-sha256=a1f92e839ed952de9ec4c2aa36f86afb4fa897a631901e6428d4203d4fc700b8 -->
# phil/publishing

**Les événements d'une page qui passe en ligne ou hors ligne : son adresse canonique, sa langue, l'indexation permise
ou non, et le nom que son propriétaire lui donne.**

phil/publishing contient deux événements et leur contrat. `PagePublished` dit qu'une page est passée en ligne, ou
qu'elle l'est de nouveau avec un nouveau contenu ; `PageRetired` dit qu'elle est passée hors ligne, retirée ou
supprimée. Chacun porte l'adresse canonique de la page — un `Phil\Url\Url`, absolu et normalisé —, sa langue sous
forme d'étiquette BCP 47, un indicateur « indexable » et l'identifiant que le paquet propriétaire de la page lui
donne. Les deux implémentent `Contract\PageEvent`, qu'un écouteur lit sans se soucier duquel des deux il a reçu.
**Il ne** répartit rien, ne stocke aucune page, ne construit aucune adresse et ne décide pas de ce qui est
indexable : c'est le paquet propriétaire de la page qui le fait, une fois le changement stocké.

Il sert aux applications où plusieurs paquets suivent ce que les lecteurs peuvent voir : un plan du site qui ajoute et
retire ses entrées, un cache HTTP qui purge une page, un signal aux moteurs de recherche, un concentrateur de fils, une
boîte d'envoi de fédération. Le propriétaire des pages répartit les événements sur le répartiteur dont l'application
dispose ; chaque suiveur les écoute, et aucun ne connaît les autres. D'autres événements du cycle de vie d'une page
pourront s'ajouter à ces deux-là.

phil/publishing est une bibliothèque PHP simple qui dépend de `phil/url`, sans dépendance à un framework. Ses
événements sont de simples objets immuables, répartis par un répartiteur PSR-14 comme l'EventDispatcher de Symfony.

## État

| Partie | État |
|---|---|
| `PagePublished` et `PageRetired`, avec leurs contrôles | Implémenté et testé |
| `Contract\PageEvent` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Les événements](doc/fr/events.md) | Les quatre champs, leurs contrôles, quand un propriétaire répartit chaque événement et comment un écouteur les lit. |
| [Points d'extension](doc/fr/extension-points.md) | Ce sur quoi s'appuient un propriétaire et un écouteur, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/publishing
```

Le propriétaire d'une page répartit, une fois la page stockée :

```php
use Phil\Publishing\PagePublished;
use Phil\Url\Url;

$dispatcher->dispatch(new PagePublished(
    Url::fromString('https://example.org/fr/news/opening'),
    'fr',
    indexable: true,
    ownerId: 'news:42',
));
```

Un suiveur écoute, ici avec l'EventDispatcher de Symfony :

```php
use Phil\Publishing\PageRetired;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PurgeRetiredPage
{
    public function __invoke(PageRetired $event): void
    {
        $this->cache->purge($event->canonicalUrl->toString());
    }
}
```

## Prérequis

PHP 8.5 ou une version plus récente, et `phil/url`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
