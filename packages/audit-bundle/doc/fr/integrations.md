<!-- phil-dev source-sha256=da818e61378efd3aa221225400c5ec900b45b09f67bbcef931553a9b2c1dce25 -->
# Intégrations

La piste lit son contexte par des ports, chacun avec un adaptateur que le bundle enregistre quand le composant qu'il lit
est installé. Une application qui en décide autrement fait de ce port l'alias de son propre service; son alias
l'emporte.

| Port | Adaptateur | Enregistré quand |
|---|---|---|
| `Application\Contract\TenantScope` | `TenancyTenantScope`, sur le `TenantContext` de `phil/tenancy-bundle` | `phil/tenancy-bundle` est installé et enregistré |
| `Application\Contract\ActorResolver` | `SecurityActorResolver`, sur le stockage de jetons du composant Security | le composant Security est installé et SecurityBundle enregistré |
| `Application\Contract\AuditNotifier` | `MessengerAuditNotifier`, sur un bus de Messenger | `dispatch_bus` nomme un bus |
| `Domain\Contract\FactExporter` | `JsonLinesFactExporter`, `OtlpLogExporter` sur HttpClient de Symfony | `feed` en nomme un ([dashboards.md](dashboards.md)) |

Les adaptateurs du locataire et de l'acteur sont enregistrés quand leur classe existe, et retirés par la passe de
compilation `ContextPass` quand le service qu'ils lisent n'est pas dans le conteneur.

## Les changements d'état de Symfony Workflow

Avec `workflow_segment` défini, le bundle écoute `workflow.completed` et consigne, dans ce segment, chaque transition
d'un sujet qui implémente `Application\Contract\Auditable` :

- l'action `workflow.<workflow>.<transition>`, en minuscules, chaque caractère qu'une action refuse remplacé par `_`,
  coupée à 64 caractères;
- le sujet que retourne `auditSubject()`, et l'acteur courant quand un `ActorResolver` en donne un;
- dans la charge utile, `workflow`, `transition`, `from` et `to`, les places jointes par `,` : des noms du workflow,
  jamais une valeur de l'enregistrement.

```yaml
phil_audit:
    serializer: App\Audit\CanonicalSerializerV1
    workflow_segment: states
```

```php
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditBundle\Domain\Chain\Subject;

final class Order implements Auditable
{
    public function auditSubject(): Subject
    {
        return Subject::of('order', $this->id->toString());
    }
}
```

Un sujet qui n'implémente pas `Auditable` n'est pas consigné. L'historique de la commande — `AuditLog::history()` —
contient alors ses changements d'état à côté de tout autre événement consigné à son sujet. La consignation exige un
sérialiseur : un `workflow_segment` sans sérialiseur fait échouer la construction du conteneur.

## Le compte courant du composant Security

`SecurityActorResolver` nomme l'utilisateur du jeton quand celui-ci implémente `Application\Contract\AuditActor` :

```php
use Phil\AuditBundle\Application\Contract\AuditActor;

final class User implements UserInterface, AuditActor
{
    public function auditActorId(): string
    {
        return $this->id->toString();   // stable and opaque, never the e-mail address
    }
}
```

Un utilisateur qui ne l'implémente pas n'est pas nommé : son identifiant d'utilisateur est souvent une adresse de
courriel, que la piste ne contient jamais. Le résolveur nomme l'acteur des lignes que le bundle consigne lui-même — une
transition de workflow, un export de `phil/audit-viewer`; l'application donne l'acteur de ses propres lignes dans leurs
`Facts`.

## Le locataire de `phil/tenancy-bundle`

`TenancyTenantScope` lit le locataire dans lequel agissent la requête, le message ou la commande, et ne le change
jamais. Une ligne consignée sans locataire propre est estampillée du locataire courant, et `AuditLog` y limite chaque
lecture. Hors de tout locataire, les lignes sont consignées sans locataire et le journal est lu en entier. Avec
`site_segments`, les lignes de chaque locataire vont aussi dans une chaîne qui lui est propre, et `ChainStatus` ne
montre à un locataire que ses propres segments ([sites.md](sites.md)).

## L'envoi aux tableaux de bord avec Messenger

Avec `dispatch_bus` qui nomme un bus, chaque ligne consignée est envoyée sous la forme d'un message
`Application\Message\AuditRecorded` qui porte le segment et la séquence de la ligne, et rien de ce qu'elle dit. Un
*handler* qui a besoin de la ligne la relit par `AuditLog::row()`, de sorte qu'aucun champ de la piste ne voyage sur un
transport :

```yaml
phil_audit:
    dispatch_bus: messenger.bus.default

framework:
    messenger:
        routing:
            Phil\AuditBundle\Application\Message\AuditRecorded: async
```

```php
#[AsMessageHandler]
final readonly class FeedSecurityDashboard
{
    public function __construct(private AuditLog $log, private Dashboard $dashboard) {}

    public function __invoke(AuditRecorded $message): void
    {
        $row = $this->log->row($message->segment, $message->sequence);

        if (null !== $row && str_starts_with($row->facts->action ?? '', 'account.')) {
            $this->dashboard->count($row->facts->action, $row->recordedAt);
        }
    }
}
```

Le message est envoyé une fois la ligne dans la piste, après une concurrence retentée. Acheminé vers un transport
asynchrone, un tableau de bord est alimenté sans ralentir la requête qui a consigné la ligne; sur le bus synchrone,
l'échec d'un *handler* atteint l'appelant de `record()` alors que la ligne est déjà écrite.

Quand une `feed` est aussi configurée, le bundle enregistre son propre *handler*, `FeedOnRecorded`, qui envoie les
faits du segment du message à partir de l'emplacement de l'alimentation : le *handler* ci-dessus sert à ce que
l'alimentation ne fait pas, comme compter dans l'application elle-même ([dashboards.md](dashboards.md)).
