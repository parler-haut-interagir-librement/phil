<!-- phil-dev source-sha256=3dc950afbdf41a9f9f428fb7439d4b35d756b75088767b045dcd55a5fb6ee1e3 -->
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
enregistrée, un statut de 400 à 499 autre que 408 et 429.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\LtiBundle\Application\Message\PublishScore: async
```

## Les autres services

`AgsClient` et `NrpsClient` sont des services publics, les jetons d'accès étant demandés et mis en cache par le bundle ;
leurs méthodes sont décrites dans la documentation de `phil/lti`, sur ses pages des Assignment and Grade Services et du
Names and Role Provisioning Service.
