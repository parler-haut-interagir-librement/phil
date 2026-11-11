<!-- phil-dev source-sha256=a5f5496f8239005a8fe691ea3d5119fc3768fc9ea5b61365292deef833e658e5 -->
# phil/edit-lock-bundle

**Un rédacteur par bloc : des baux d'édition, la présence et les enregistrements de blocs poussés par Mercure, pour les
applications Symfony.**

phil/edit-lock-bundle permet à plusieurs personnes de modifier la même ressource sans écraser le travail des autres. Un
rédacteur qui entre dans un bloc d'un document en prend le bail : personne d'autre ne peut écrire ce bloc tant que le
bail n'est pas libéré, qu'il n'expire pas faute de pulsation, ou qu'une personne autorisée par l'application, un
propriétaire ou un administrateur, ne le reprend pas. Un bail peut aussi couvrir une ressource entière, ce qui exclut
alors tout bail d'autrui sur ses parties. Chaque bloc que le détenteur enregistre est vérifié par `phil/ujo`, remis à
l'application et poussé aux autres rédacteurs avec la liste de qui modifie quoi, sous forme de Turbo Streams, par un hub
Mercure. Dans le navigateur, un contrôleur Stimulus prend les baux à mesure que le rédacteur passe d'un bloc à l'autre,
ferme et nomme les blocs que d'autres détiennent, et applique leurs enregistrements à l'éditeur de
`phil/block-editor-bundle` dès leur arrivée. La granularité est le bloc : il n'y a aucune fusion de caractères.

Il s'adresse à toute application Symfony où des gens écrivent ensemble : une page de wiki, un brouillon partagé, les
notes d'un meneur de jeu, le procès-verbal d'une réunion. L'application nomme les ressources (`article:42`,
`wiki/home`), dit qui écrit et ce que chacun peut modifier, et décide où vont les blocs enregistrés ; le bundle conserve
les baux et transporte les messages. Une ressource n'a pas à être un document : un formulaire, un enregistrement ou un
fichier peut faire l'objet d'un bail entier, depuis PHP, sans le contrôleur.

C'est un bundle Symfony sur `phil/ujo`, Doctrine et, en option, MercureBundle. Les baux résident dans une table, écrits
par des requêtes de comparaison et échange dans une transaction qui verrouille la ligne de la ressource, afin que deux
requêtes en concurrence pour un bloc, ou pour la ressource entière et l'un de ses blocs, ne gagnent jamais toutes les
deux ; ou en mémoire pour les tests. Chaque point de terminaison vérifie l'en-tête du contrôleur et le jeton CSRF sans
état de Symfony. Sans Mercure, le contrôleur demande la présence à chaque pulsation, et les enregistrements ne sont pas
poussés. Le contrôleur est servi par AssetMapper, sans étape de construction. Mercure, Turbo Streams et UJO (Universal
JSON Output) sont les formats qu'il parle ; il ne dépend d'aucune autre bibliothèque de verrouillage ou de
collaboration.

## État

| Partie | État |
|---|---|
| `LeaseKey`, `Holder`, `Lease` : une ressource ou un bloc, son détenteur, jusqu'à quand | Implémenté et testé |
| `EditLocks` : prendre, pulser, libérer, quitter, reprendre, présence, purge | Implémenté et testé |
| `LeaseRepository` ; `DoctrineLeaseRepository` par comparaison et échange, une revendication à la fois par ressource ; `InMemoryLeaseRepository` | Implémenté et testé, sur SQLite, et deux revendications concurrentes sur MySQL et PostgreSQL |
| `BlockSaves` : des blocs vérifiés par `phil/ujo`, écrits par leur détenteur, conservés par le `BlockStore` de l'application | Implémenté et testé |
| `MercureBroadcaster`, `TurboStreams` : la présence, les blocs enregistrés et retirés sous forme de Turbo Streams | Implémenté et testé, aussi contre un vrai hub |
| `EditLockController`, les routes, `ClientConfig` : les points de terminaison et la configuration du contrôleur | Implémenté et testé |
| Le contrôleur `collaboration` : des baux qui suivent le rédacteur, la liste des rédacteurs, la reprise, les enregistrements distants appliqués à l'éditeur de blocs | Implémenté ; modules purs testés sous Node, vérifié dans un navigateur avec deux rédacteurs ([tests](doc/fr/testing.md)) |
| La fusion des caractères que deux rédacteurs tapent dans un même bloc | Ne fait pas partie du bundle : un rédacteur par bloc |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la table, les routes, Mercure, le contrôleur et la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque option, et les services que l'application écrit. |
| [Baux](doc/fr/leases.md) | Les clés, les détenteurs, les durées, les pulsations, l'expiration, la reprise, la libération, la présence et la concurrence entre requêtes. |
| [Blocs](doc/fr/blocks.md) | L'enregistrement et le retrait des blocs, la vérification par `phil/ujo`, le magasin de blocs de l'application. |
| [Mercure](doc/fr/mercure.md) | Les sujets, les mises à jour privées, les Turbo Streams poussés, et le fonctionnement sans hub. |
| [Points de terminaison](doc/fr/endpoints.md) | Chaque route, son corps, ses réponses, l'en-tête du contrôleur et son jeton CSRF. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur, sa configuration, ses modules, et son fonctionnement avec l'éditeur de blocs. |
| [Exceptions](doc/fr/exceptions.md) | Les exceptions du bundle, et les réponses de ses points de terminaison. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, le vrai hub, le seuil de mutation et les vérifications dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/edit-lock-bundle symfony/mercure-bundle
```

```yaml
# config/packages/phil_edit_lock.yaml
phil_edit_lock:
    holder_provider: App\Collaboration\CurrentWriter   # a HolderProvider
    policy: App\Collaboration\WritersPolicy            # an EditPolicy
    block_store: App\Collaboration\DraftBlocks          # a BlockStore, optional
```

```twig
<div data-controller="phil--edit-lock-bundle--collaboration"
     data-phil--edit-lock-bundle--collaboration-config-value="{{ collaboration|json_encode }}">
    {{ form_row(form.body) }}  {# the block editor of phil/block-editor-bundle #}
    <aside data-phil--edit-lock-bundle--collaboration-target="presence"></aside>
</div>
```

`collaboration` vaut `ClientConfig::for('article:42', $holder)`, fourni par le contrôleur de la page.

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4 et ORM 3, `phil/ujo`. Pour les mises à jour poussées, MercureBundle
et un hub Mercure ; pour le contrôleur, Stimulus 3 et `phil/block-editor-bundle`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
