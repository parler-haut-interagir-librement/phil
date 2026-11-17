<!-- phil-dev source-sha256=9854695f4ed8b80482b3af01be05c00868a173e41db0edc7261539fd0f810192 -->
# L'outil

## Un lancement, étape par étape

1. La plateforme appelle `phil_lti_login` (`/lti/login`) par GET ou POST. `ToolController::login()` vérifie l'initiation
   avec `LoginInitiator` — une plateforme et un déploiement enregistrés, une cible sur l'origine de l'application — et
   redirige le navigateur vers la plateforme avec la requête d'authentification. L'état et le nonce attendent dans la
   table.
2. La plateforme envoie `id_token` et `state` par POST à `phil_lti_launch` (`/lti/launch`). `LtiLaunchAuthenticator`
   vérifie le lancement avec `LaunchValidator`, trouve son utilisateur et le connecte avec un `LtiToken`.
3. `ToolController::launch()` répartit `LaunchReceived`. Un écouteur répond au lancement ; sans réponse, le navigateur
   est redirigé (303) vers la cible du lancement.

Un lancement qui échoue à une vérification répond 401 avec la raison, en texte brut, jamais avec le jeton.

## Le jeton et l'utilisateur

```php
use Phil\LtiBundle\Infrastructure\Security\LtiToken;

$token = $security->getToken();

if ($token instanceof LtiToken) {
    $launch = $token->launch;             // Phil\Lti\Message\Launch
    $launch->message->context?->id;
    $launch->roles()->isInstructor();
}
```

Sans `LaunchUsers`, l'utilisateur est un `LtiUser`, nommé par le `userKey()` du lancement — l'empreinte de l'émetteur,
du client et du sujet —, avec `ROLE_LTI` et, selon ses rôles, `ROLE_LTI_INSTRUCTOR` ou `ROLE_LTI_LEARNER`. Il ne vit
que dans la session ; `LtiUserProvider` le rafraîchit.

Pour connecter la personne au compte propre de l'application, implémentez `LaunchUsers` :

```php
use Phil\Lti\Message\Launch;
use Phil\LtiBundle\Application\Contract\LaunchUsers;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class LearnerAccounts implements LaunchUsers
{
    public function user(Launch $launch): UserInterface
    {
        return $this->accounts->findOrCreateByLtiKey($launch->userKey() ?? throw new \RuntimeException('Anonymous launch.'));
    }
}
```

Liez-lui par alias `Phil\LtiBundle\Application\Contract\LaunchUsers`, et donnez au pare-feu des lancements le
fournisseur d'utilisateurs de l'application.

## Répondre à un lancement

```php
#[AsEventListener]
final readonly class OpenTheResource
{
    public function __invoke(LaunchReceived $event): void
    {
        $launch = $event->launch;

        if ($launch->isDeepLinking()) {
            $event->setResponse(new RedirectResponse($this->router->generate('app_picker')));

            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('app_resource', ['id' => $launch->message->custom['resource'] ?? ''])));
    }
}
```

## Deep Linking

Le sélecteur de l'application répond avec `DeepLinkingResponder` et `FormPostResponder` :

```php
use Phil\Lti\DeepLinking\ResourceLinkItem;
use Phil\Lti\Tool\DeepLinkingResponder;
use Phil\LtiBundle\Infrastructure\Http\FormPostResponder;

public function pick(DeepLinkingResponder $responder, FormPostResponder $form): Response
{
    $launch = $this->security->getToken()->launch;

    return $form->respond($responder->respond($launch, [new ResourceLinkItem($this->quizUrl(3), 'Quiz 3')]));
}
```

La page envoie la réponse signée à l'adresse de retour de la plateforme par POST et se soumet d'elle-même.

## Envoyer des notes

```php
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\Score;
use Phil\LtiBundle\Application\ScorePublisher;

$publisher->publish($launch, new Score($launch->subject(), ActivityProgress::Completed, GradingProgress::FullyGraded, $clock->now(), 8.0, 10.0));
```

`publish()` refuse un lancement dont le point de terminaison n'accorde pas la portée des notes ou ne nomme aucun line
item, garde la note comme en attente, et l'envoie : par le bus nommé par `message_bus`, le message `PublishScore` ne
portant que l'identifiant de la note, ou tout de suite sans bus. Une fois que la plateforme la prend, la note en
attente est retirée et `ScorePublished` est répartie. `PublishScoreHandler` laisse Messenger reprendre un échec du
réseau ou de la plateforme, et s'arrête pour un refus que la plateforme répétera — une plateforme qui n'est plus
enregistrée, un statut de 400 à 499 autre que 408 et 429, une requête qui ne peut pas être envoyée.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\LtiBundle\Application\Message\PublishScore: async
```

### La reprise

Le message ne porte que l'identifiant de la note ; la note elle-même attend dans le magasin, qui enregistre si le bus a
pris le message. Une note dont le message n'a jamais été pris — le processus s'est arrêté entre l'enregistrement et
l'envoi, le transport l'a refusé ou, sans bus, la plateforme était inaccessible — est renvoyée par
`phil:lti:recover-scores`, exécutée toutes les quelques minutes :

```bash
bin/console phil:lti:recover-scores --delay=300 --limit=100
```

La commande réclame chaque note enregistrée, ou réclamée en dernier, il y a `--delay` secondes ou plus, en une seule
étape atomique par note, puis envoie de nouveau son message, ou l'envoie sans bus : deux exécutions simultanées ne
prennent jamais la même note dans le délai, et une exécution après une autre n'envoie rien de nouveau. Quand le
transport refuse le message dans `publish()`, la note est tout de même gardée et son identifiant renvoyé ; la reprise
l'envoie. Envoyer une note deux fois est sans conséquence : la plateforme garde la dernière par son horodatage. Un
message que le bus a pris mais qui a échoué pour de bon reste dans le transport des échecs de Messenger, où
`messenger:failed:retry` l'envoie de nouveau.

### Les notes refusées

Un refus que la plateforme répétera marque la note refusée, avec le moment et la raison, et répartit `ScoreRefused` :
une note refusée n'est jamais renvoyée, ni reprise. Elle reste comme trace pendant `tool.refused_score_retention`
secondes — trente jours par défaut —, puis `phil:lti:purge` la retire et écrit son identifiant, sa plateforme, son
client, son moment et sa raison, jamais la note elle-même. Une application qui garde sa propre trace écoute
`ScoreRefused`.

### Une boîte d'envoi

`PendingScores` a la forme d'une boîte d'envoi transactionnelle (*transactional outbox*) : `save()` écrit l'entrée,
`markDispatched()` est l'acquittement du relais, `claim()` son interrogation, `refused()` et `delete()` les lettres
mortes et leur purge. Une application qui a sa propre boîte d'envoi implémente le port par-dessus, et une version
ultérieure du bundle pourra confier le relais à un paquet de boîte d'envoi partagé sans changer le port.

## Les autres services

`AgsClient` et `NrpsClient` sont des services publics, les jetons d'accès étant demandés et mis en cache par le bundle ;
leurs méthodes sont décrites dans la documentation de `phil/lti`, sur ses pages des Assignment and Grade Services et du
Names and Role Provisioning Service.
