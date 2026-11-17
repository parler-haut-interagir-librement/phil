# The tool

## A launch, step by step

1. The platform calls `phil_lti_login` (`/lti/login`) by GET or POST. `ToolController::login()` checks the initiation
   with `LoginInitiator` — a registered platform and deployment, a target on the application's origin — and redirects
   the browser to the platform with the authentication request. The state and the nonce wait in the table.
2. The platform posts `id_token` and `state` to `phil_lti_launch` (`/lti/launch`). `LtiLaunchAuthenticator` verifies
   the launch with `LaunchValidator`, finds its user and signs it in with an `LtiToken`.
3. `ToolController::launch()` dispatches `LaunchReceived`. A listener answers the launch; without an answer, the browser
   is redirected (303) to the target of the launch.

A launch that fails a check answers 401 with the reason, as plain text, never with the token.

## The token and the user

```php
use Phil\LtiBundle\Infrastructure\Security\LtiToken;

$token = $security->getToken();

if ($token instanceof LtiToken) {
    $launch = $token->launch;             // Phil\Lti\Message\Launch
    $launch->message->context?->id;
    $launch->roles()->isInstructor();
}
```

Without `LaunchUsers`, the user is an `LtiUser`, named by the launch's `userKey()` — the hash of the issuer, the client
and the subject —, with `ROLE_LTI` and, from its roles, `ROLE_LTI_INSTRUCTOR` or `ROLE_LTI_LEARNER`. It lives in the
session only; `LtiUserProvider` refreshes it.

To sign the person in to the application's own account, implement `LaunchUsers`:

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

Alias `Phil\LtiBundle\Application\Contract\LaunchUsers` to it, and give the firewall of launches the application's user
provider.

## Answering a launch

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

The picker of the application answers with `DeepLinkingResponder` and `FormPostResponder`:

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

The page posts the signed response to the platform's return address and submits itself.

## Sending scores

```php
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\Score;
use Phil\LtiBundle\Application\ScorePublisher;

$publisher->publish($launch, new Score($launch->subject(), ActivityProgress::Completed, GradingProgress::FullyGraded, $clock->now(), 8.0, 10.0));
```

`publish()` refuses a launch whose endpoint does not grant the scope of scores or names no line item, keeps the score as
pending, and sends it: through the bus named by `message_bus`, the message `PublishScore` carrying the score's
identifier only, or at once without a bus. Once the platform takes it, the pending score is removed and `ScorePublished`
is dispatched. `PublishScoreHandler` lets Messenger retry a failure of the network or of the platform, and stops for a
refusal the platform will repeat — a platform no longer registered, a status of 400 to 499 other than 408 and 429, a
request that cannot be sent.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\LtiBundle\Application\Message\PublishScore: async
```

### Recovery

The message carries the identifier of the score only; the score itself waits in the store, which records whether the
bus took the message. A score whose message was never taken — the process stopped between the save and the dispatch,
the transport refused it, or, without a bus, the platform could not be reached — is sent again by
`phil:lti:recover-scores`, run every few minutes:

```bash
bin/console phil:lti:recover-scores --delay=300 --limit=100
```

The command claims each score saved, or last claimed, `--delay` seconds ago or more, in one atomic step per score, then
dispatches its message again, or sends it without a bus: two runs at once never take the same score within the delay,
and a run after another sends nothing new. When the transport refuses the message in `publish()`, the score is kept all
the same and its identifier returned; the recovery sends it. Sending a score twice is harmless: the platform keeps the
latest by its timestamp. A message the bus took but that failed for good stays in Messenger's failure transport, where
`messenger:failed:retry` sends it again.

### Refused scores

A refusal the platform will repeat marks the score refused, with the moment and the reason, and dispatches
`ScoreRefused`: a refused score is never sent again, nor recovered. It stays as a trace for
`tool.refused_score_retention` seconds — thirty days by default —, then `phil:lti:purge` removes it and writes its
identifier, platform, client, moment and reason, never the score itself. An application that keeps its own trace
listens to `ScoreRefused`.

### An outbox

`PendingScores` has the shape of a transactional outbox: `save()` writes the entry, `markDispatched()` is the
acknowledgement of the relay, `claim()` its poll, `refused()` and `delete()` the dead letters and their purge. An
application with an outbox of its own implements the port over it, and a later version of the bundle may hand the relay
to a shared outbox package without changing the port.

## The other services

`AgsClient` and `NrpsClient` are public services, with access tokens asked for and cached by the bundle; their methods
are described in the documentation of `phil/lti`, on its pages of the Assignment and Grade Services and of the Names and
Role Provisioning Service.
