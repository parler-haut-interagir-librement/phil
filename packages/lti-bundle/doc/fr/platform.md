<!-- phil-dev source-sha256=e5292f43df11b0c4a762d964ca3f60cbf8273bda9b49e130183ea0b289973062 -->
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
L'outil y envoie sa réponse par POST ; le bundle la vérifie avec `DeepLinkingReturn`, la garde, et répartit
`DeepLinkingReturned`. Un écouteur place les éléments et répond au navigateur ; sans réponse, le bundle affiche une
courte page avec le nombre d'éléments et le message de l'outil.

### Reprendre une réponse

Le `nonce` d'une réponse est pris une seule fois : le jeton que le navigateur a envoyé n'est jamais accepté de nouveau.
Pour qu'un écouteur qui échoue ne fasse pas perdre la réponse, `DeepLinkingHandler` la garde avant d'en informer
l'application — sa clé, son client et les revendications avec lesquelles elle a été vérifiée, dans la table
`deep_linking` ou en mémoire, pendant `platform.deep_linking_retention` secondes, un jour par défaut — et la marque
traitée une fois que chaque écouteur est revenu. Les revendications sont celles que l'outil a choisies pour la
plateforme — éléments de contenu, le `data` que la plateforme a mis dans sa requête, messages — : aucun identifiant de
connexion et aucune revendication sur une personne, donc elles sont gardées telles quelles, 64 Kio de JSON au plus.

`DeepLinkingReturned::$key` nomme la réponse pour de bon : un hachage de son client et de son `nonce`, le même quand la
réponse est annoncée de nouveau. Un écouteur est idempotent par cette clé — il place les éléments d'une clé une seule
fois, et retrouve ce qu'il a placé la première fois quand il revoit la clé. Quand un écouteur lève une exception, le
navigateur reçoit l'erreur et la réponse reste gardée, non traitée. L'application l'annonce de nouveau, à partir des
revendications gardées et sans le jeton :

```php
use Phil\LtiBundle\Application\DeepLinkingHandler;

$event = $handler->resume($key);                 // null when nothing is kept under the key, or it expired
foreach ($handler->unhandled(300) as $key) { … }  // the responses not handled after five minutes
```

```bash
bin/console phil:lti:resume-deep-linking <key>       # one response, handled or not
bin/console phil:lti:resume-deep-linking --delay=300 # every response not handled after five minutes
```

`DeepLinkingReturned::$resumed` est à true pour une réponse annoncée de nouveau ; un écouteur n'a alors aucun navigateur
à qui répondre, et sa réponse n'est pas employée. `phil:lti:purge` retire les réponses expirées.

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
`role`. Un corps est du JSON de 64 Kio au plus.

Une page de line items ou de membres contient 100 entrées quand l'outil ne demande aucun `limit`, et 1 000 au plus quoi
qu'il demande. Son `Link: <…>; rel="next"` porte les filtres, le `limit` auquel la page a été coupée et l'`offset` de la
page suivante, et il est présent chaque fois qu'une page suivante existe : aussi sans `limit`, et avec un `limit` au-delà
de 1 000.

Une note plus ancienne que la dernière de l'apprenant répond 409. La comparaison et l'écriture sont une seule étape
atomique du magasin : de deux notes d'un même apprenant envoyées en même temps, la plus récente reste, quel que soit
l'ordre d'arrivée, et l'autre répond 409. Une note du même moment que celle qui est gardée la remplace.

Une note demande un jeton de la portée `score` et rien de plus, comme celui que demande `AgsClient::publishScore()` côté
outil : le line item que porte `ScoreReceived` est lu dans le magasin une fois la note gardée, sans les portées des line
items.

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
| `DeepLinkingReturned` | Une réponse de Deep Linking a été vérifiée et gardée, ou est annoncée de nouveau (`resumed` à true), sous sa `key`. |
