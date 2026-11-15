<!-- phil-dev source-sha256=fa76e723dd00448ccba50f4514e74457e9a44c8ed490fca519345e6467afa302 -->
# La plateforme

## Démarrer un lancement

`LaunchStarter` donne l'adresse de l'initiation de connexion d'un outil ; l'application l'ouvre dans un cadre ou une
fenêtre :

```php
use Phil\Lti\Contract\ToolRegistrations;
use Phil\Lti\Platform\LaunchStarter;

$tool = $tools->find('client-42');
$url = $starter->loginUrl($tool, '1', $launchMessages->loginHint($member), $targetLinkUri, messageHint: $resourceLinkId);
```

## Les lancements de l'application

Le côté plateforme a besoin des `LaunchMessages` de l'application : ce par quoi la plateforme reconnaît un membre, et le
message d'un lancement, retrouvé par son indice de message, pour le membre connecté.

```php
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Registration\ToolRegistration;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;

final readonly class CourseLaunches implements LaunchMessages
{
    public function __construct(private PlatformUrls $urls, private Placements $placements) {}

    public function loginHint(UserInterface $user): string
    {
        return $user->getUserIdentifier();
    }

    public function message(AuthenticationRequest $request, ToolRegistration $tool, UserInterface $user): LaunchMessage
    {
        $placement = $this->placements->forMember((string) $request->messageHint, $user);

        return new LaunchMessage(
            MessageType::ResourceLink,
            $placement->deploymentId,
            $placement->target,
            new Person($placement->subjectFor($user)),
            Roles::of([$placement->teaches($user) ? ContextRole::Instructor : ContextRole::Learner]),
            new ResourceLink($placement->id, $placement->title),
            new Context($placement->courseId, $placement->courseCode, $placement->courseTitle),
            ags: $this->urls->agsEndpoint($tool, $placement->courseId, $placement->lineItemId),
            nrps: $this->urls->nrpsEndpoint($placement->courseId),
        );
    }
}
```

`PlatformUrls` donne les adresses des services pour les revendications, avec les portées pour lesquelles l'outil est
enregistré.

## Le point de terminaison d'autorisation

`phil_lti_platform_authorize` (`/lti/platform/authorize`), derrière le pare-feu des membres, lit la requête
d'authentification par GET ou POST. Une requête dont le client ou l'adresse de redirection n'est pas enregistré répond
400. Une fois l'adresse de redirection connue, `LaunchMessages` construit le message et `LaunchIssuer` le signe ; la
page envoie l'`id_token` et le `state` à l'outil par POST et se soumet d'elle-même. Un refus de l'application —
`InvalidRequest` levée par `message()`, un indice de connexion qui n'est pas celui du membre — est renvoyé à l'outil par
POST comme OpenID Connect écrit les erreurs : `error`, `error_description` et le `state`. Une requête sans membre
connecté répond 401.

## Deep Linking

L'application lance une requête de Deep Linking avec un message de type `MessageType::DeepLinking` et ses
`DeepLinkingSettings`, l'adresse de retour étant `phil_lti_platform_deep_linking` et `data` nommant le placement.
L'outil y envoie sa réponse par POST ; le bundle la vérifie avec `DeepLinkingReturn` et répartit `DeepLinkingReturned`.
Un écouteur place les éléments et répond au navigateur ; sans réponse, le bundle affiche une courte page avec le nombre
d'éléments et le message de l'outil.

## Le point de terminaison des jetons

`phil_lti_platform_token` (`/lti/platform/token`) répond `TokenEndpoint::issue()` en JSON, jamais mis en cache ; un
refus avec son code d'erreur OAuth, 401 pour un client qui ne s'authentifie pas, 400 sinon. Son adresse est le
`platform.token_url` de la configuration, celle que les outils enregistrent.

## Les services

| Route | Méthode | Chemin sous le préfixe | Portée |
|---|---|---|---|
| `phil_lti_platform_line_items` | GET, POST | `/contexts/{context}/lineitems` | `lineitem`, `lineitem.readonly` pour lire |
| `phil_lti_platform_line_item` | GET, PUT, DELETE | `/contexts/{context}/lineitems/{id}` | la même |
| `phil_lti_platform_scores` | POST | `/contexts/{context}/lineitems/{id}/scores` | `score` |
| `phil_lti_platform_results` | GET | `/contexts/{context}/lineitems/{id}/results` | `result.readonly` |
| `phil_lti_platform_memberships` | GET | `/contexts/{context}/memberships` | `contextmembership.readonly` |

Chaque appel porte un jeton porteur : sans jeton, 401 avec `WWW-Authenticate: Bearer error="invalid_token"` ; sans sa
portée, 403 avec `error="insufficient_scope"`. Un contexte où l'outil n'est pas placé, ou un line item qui n'est pas
celui de l'outil, répond 404. Les listes prennent `limit` et `offset`, et donnent la page suivante dans un en-tête
`Link` ; les line items prennent `resource_link_id`, `resource_id` et `tag`, les résultats `user_id`, les adhésions
`role`. Un corps est du JSON de 64 Kio au plus. Une note plus ancienne que la dernière de l'apprenant répond 409.

## La liste de membres et le carnet de notes

Les services lisent le `Roster` de l'application — ses contextes, les outils placés dans chacun, leurs membres — ;
tant que l'application ne lie pas `Phil\Lti\Contract\Roster` par alias à le sien, une liste vide répond 404 pour
chaque contexte. Les line items et les dernières notes sont gardés dans les tables du bundle ; le carnet de notes reste
celui de l'application, qui écoute :

| Événement | Quand |
|---|---|
| `LineItemSaved` | Un outil a créé (`created` vrai) ou modifié un line item. |
| `LineItemDeleted` | Un outil a supprimé un line item, avec ses notes. |
| `ScoreReceived` | Une note a été gardée comme la dernière de l'apprenant sur un line item. |
| `DeepLinkingReturned` | Une réponse de Deep Linking a été vérifiée. |
