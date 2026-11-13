<!-- phil-dev source-sha256=efae755e73360668da7819139c401a222ea0651a11ff30bdec521c1bd99f7496 -->
# phil/poll-bundle

**Des sondages et leurs bulletins pour les applications Symfony : à choix unique, multiple ou classé, anonymes ou non,
dépouillés à la demande, avec le vote unique transférable pour plusieurs sièges et une interface de vote pour les
afficher.**

phil/poll-bundle place le moteur de vote de `phil/poll` derrière les services d'une application Symfony. Un sondage a
une question, de deux à cent options avec libellé, les règles de ses bulletins, la méthode qui les dépouille et, quand
l'application le veut, une ouverture et une fermeture. Les gens déposent un bulletin, le modifient ou le retirent
pendant que le sondage est ouvert ; les personnes qui le gèrent le ferment, en changent les dates ou le suppriment ; les
résultats sont comptés quand on les demande, et affichés comme le dit la visibilité du sondage — en tout temps, après
le vote, après la fermeture, ou à ses gestionnaires seulement.

Un sondage qui pourvoit plusieurs sièges — un conseil, un jury — est dépouillé par le vote unique transférable, selon
la méthode de Meek, en nombres exacts : chaque tour est publié, avec son quota, les votes et le facteur de rétention de
chaque option et la valeur épuisée, si bien que toute personne qui détient les bulletins rejoue le dépouillement
jusqu'à la dernière décimale.

Un sondage peut être anonyme : ses bulletins sont alors gardés à l'écart de leurs votants. Le bundle note qu'un votant a
participé, pour que personne ne vote deux fois, mais rien ne lie un bulletin à cette personne : le bulletin a un
identifiant aléatoire, aucune heure, et seulement l'empreinte du reçu remis à son votant, avec lequel celui-ci peut le
modifier pendant que le sondage est ouvert.

Il sert à toute application Symfony où des gens votent, et aux bundles qui reposent sur un vote : un sondage de
disponibilités qui trouve une date, un questionnaire, une boîte à idées votées par les membres, l'assemblée générale
d'une association. Un sondage peut appartenir à l'une des ressources de l'application — une idée, une réunion — nommée
par une référence de `phil/resource` (`idea:42`), tout comme ses votants (`user:7`). L'application dit qui peut créer un
sondage, voter et le gérer ; elle peut ajouter ses propres méthodes de dépouillement à côté des quatre de la
bibliothèque. Les bundles qui reposent sur lui affichent un sondage avec les mêmes vues et les mêmes gabarits : le
formulaire d'un bulletin et les résultats, dans le balisage des recettes du kit PHIL UX, sans JavaScript, et un
événement du DOM quand les résultats d'un sondage fermé sont révélés, sur lequel joue la célébration du kit.

C'est un bundle Symfony qui repose sur `phil/poll`, `phil/resource` et Doctrine ORM : les sondages, les bulletins et
les participations vivent dans trois tables, ou en mémoire pour les tests. Les services ne gardent aucun état entre les
requêtes et sont sûrs dans un *worker* de longue durée ; chaque action diffuse un événement qui nomme le sondage et rien
d'autre. Un dépouillement peut être gardé dans un pool de cache jusqu'au prochain bulletin, quand l'application en
nomme un.

## État

| Partie | État |
|---|---|
| `Poll`, `Vote`, `Participation` : les sondages, les bulletins nominatifs et anonymes, les participations des sondages anonymes | Implémenté et testé |
| `Polls` : créer, déposer, modifier, modifier par reçu, retirer, fermer, changer les dates, supprimer, compter, en consultant la politique | Implémenté et testé |
| Les résultats affichés selon la visibilité de chaque sondage | Implémenté et testé |
| Les méthodes de dépouillement de l'application, autoconfigurées à côté de celles de la bibliothèque | Implémenté et testé |
| Le vote unique transférable selon la méthode de Meek, en nombres exacts, avec ses tours publiés | Implémenté, testé, soumis au fuzzing et mesuré par un banc d'essai |
| Les dépouillements gardés dans un pool de cache jusqu'au prochain bulletin, derrière une option | Implémenté et testé |
| L'interface de vote : les vues, les gabarits dans le balisage des recettes du kit, la lecture d'un formulaire soumis | Implémenté et testé |
| L'événement du DOM des résultats révélés, pour la célébration du kit | Implémenté et testé |
| Stockages Doctrine et en mémoire, ceux en mémoire vidés entre deux requêtes | Implémenté et testé, sur SQLite |
| Les événements qui nomment le sondage : créé, bulletin déposé, modifié, retiré, fermé, supprimé | Implémenté et testé |
| Les routes et les contrôleurs pour voter | Hors du bundle : les bundles qui reposent sur lui et l'application gardent les leurs |
| Les notes, le jugement majoritaire ; les échelles, les nuages de mots, les indices de recommandation et les remue-méninges, sans pointage | Pas commencé : construits quand un site les demande |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, les tables, un premier sondage. |
| [Configuration](doc/fr/configuration.md) | Chaque option et les services enregistrés. |
| [Sondages et bulletins](doc/fr/polls-and-ballots.md) | Créer un sondage, ses dates, déposer, modifier et retirer un bulletin, gérer un sondage. |
| [Anonymat](doc/fr/anonymity.md) | Ce que garde un sondage anonyme, le reçu, et ce que l'anonymat ne couvre pas. |
| [Résultats](doc/fr/results.md) | Le dépouillement, la visibilité des résultats, le coût d'un dépouillement. |
| [Vote unique transférable](doc/fr/single-transferable-vote.md) | Les sondages à sièges, la méthode de Meek, les tours publiés, les nombres exacts. |
| [L'interface de vote](doc/fr/voting-interface.md) | Les vues, les gabarits, le formulaire soumis, la célébration des résultats révélés, l'accessibilité. |
| [Événements](doc/fr/events.md) | Les événements diffusés, et ce qu'ils transportent. |
| [Points d'extension](doc/fr/extension-points.md) | La politique, les méthodes de dépouillement, les stockages et les événements. |
| [Exceptions](doc/fr/exceptions.md) | Les exceptions du bundle, et celles de la bibliothèque qu'il laisse passer. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, le seuil de mutation, et comment tester du code qui utilise des sondages. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/poll-bundle
```

```php
use Phil\Poll\BallotKind;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;

final readonly class DrinksController
{
    public function __construct(
        private Polls $polls,
    ) {
    }

    public function create(ResourceReference $member): string
    {
        $poll = $this->polls->create(new PollDraft(
            'What do we drink at the next meeting?',
            [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee'), PollOption::of('cocoa', 'Cocoa')],
            BallotKind::Ranked,
            anonymous: true,
            results: ResultsVisibility::AfterClosing,
        ), $member);

        return $poll->id;
    }

    public function vote(string $poll, ResourceReference $member, array $choices): ?string
    {
        return $this->polls->cast($poll, $member, $choices)->receipt;   // shown once to the voter
    }
}
```

## Prérequis

PHP 8.5 ou plus récent avec `mbstring` et `sodium`, Symfony 8.2, Doctrine ORM 3.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
