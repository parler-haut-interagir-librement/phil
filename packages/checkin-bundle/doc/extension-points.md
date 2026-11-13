# Extension points

## Resolvers

A service implementing `Phil\Checkin\Contract\CredentialResolver` is tagged `phil_checkin.credential_resolver` by
autoconfiguration; the desk asks the tagged resolvers in the order of their priority. A resolver answers null for a
code it does not know. See `phil/checkin`'s `doc/extension-points.md` for its contract.

```yaml
services:
    App\Checkin\TicketResolver:
        tags: [{ name: phil_checkin.credential_resolver, priority: 10 }]
```

## Admission rules

The rule of a checkpoint is an expression of Symfony's ExpressionLanguage that a credential meets to enter there, on
top of the policy. The configuration gives them by checkpoint (see [Configuration](configuration.md#rules)); an
application that keeps them elsewhere — in its database, edited by its staff — implements
`Phil\CheckinBundle\Application\Contract\AdmissionRules` and names its service in `rules.provider`:

```php
final readonly class DatabaseRules implements AdmissionRules
{
    public function ruleFor(CheckpointId $checkpoint): ?string
    {
        return $this->checkpoints->rule($checkpoint->value); // null: the policy alone
    }
}
```

The bundle checks the rules of its configuration when the container compiles; it cannot check those of a provider,
so the provider checks each one with `CheckpointRules::lint()` when the staff saves it. At the door, a rule that does
not parse or fails refuses the credential.

## Credential attributes

`Phil\CheckinBundle\Application\Contract\CredentialAttributes` answers what the rules read as `attributes`: the
product of a ticket, an age band, a signed waiver, by the credential's identifier. Name the service in
`rules.attributes`. It is asked once per entry scanned at a checkpoint with a rule, and once per credential of a
manifest of such a checkpoint: answer from a cache or a single query when the manifest is large.

## Catalog

A catalog implements `Phil\Checkin\Contract\CredentialCatalog`: the credentials of a checkpoint with their codes,
for the manifests, and one by identifier, to confirm a scan made offline. The service named by `catalog` is the only
one asked. Without it, the services implementing the interface are tagged `phil_checkin.credential_catalog` by
autoconfiguration and asked together in the order of their priority: a checkpoint's manifest carries the credentials
of all of them, and a scan made offline is confirmed by the first that knows its identifier. With no catalog named or
tagged, the desks check in online only.

## Plugging a module's admission in

A module that admits its own credentials — tickets, registrations, memberships, badges — plugs them into the desks with
one service that is both a `CredentialResolver` and a `CredentialCatalog`. Autoconfiguration tags it twice; the
application configures nothing, and several modules plug in side by side:

```php
final readonly class TicketAdmission implements CredentialResolver, CredentialCatalog
{
    public function resolve(CredentialCode $code): ?Credential
    {
        $ticket = $this->tickets->bySecret($code->value);

        return null === $ticket ? null : $this->credential($ticket);
    }

    public function forCheckpoint(CheckpointId $checkpoint): iterable
    {
        foreach ($this->tickets->admittedAt($checkpoint->value) as $ticket) {
            yield new CatalogEntry(CredentialCode::of($ticket->secret), $this->credential($ticket));
        }
    }

    public function find(string $credentialId): ?Credential
    {
        $ticket = str_starts_with($credentialId, 'ticket:') ? $this->tickets->get(substr($credentialId, 7)) : null;

        return null === $ticket ? null : $this->credential($ticket);
    }

    private function credential(Ticket $ticket): Credential
    {
        return Credential::of('ticket:'.$ticket->id, $ticket->holderName, validFrom: $ticket->opensAt, validUntil: $ticket->closesAt);
    }
}
```

| What the module answers | Through |
|---|---|
| Which code is which credential | `resolve()`: null for a code it does not know, so that the next resolver is asked. |
| Which credentials a checkpoint admits, for the offline devices | `forCheckpoint()`: one entry per code; the manifest keeps those the policy and the checkpoint's rule admit. |
| The credential of a scan made offline | `find()`: by the identifier the module gave it. |
| When and how often a credential opens a checkpoint | The `Credential` itself: its window, its entries, whether it is revoked. |
| Conditions of its own — a product, an age | `CredentialAttributes` and the checkpoint's rule. |
| What follows an admission — a badge, a reward | A listener of `CheckInRecorded`. |

The module prefixes the identifiers it gives, as `ticket:` above, so that two modules never answer for the same
credential. The desk, the ledger, the policy, the rules, the manifests, the journal and the synchronisation are the
bundle's: the module brings what a credential is, nothing else.

## Ledger

`store` names a service implementing `Phil\Checkin\Contract\CheckInLedger` to keep check-ins elsewhere than in the
bundle's table. `DoctrineCheckInLedger` is the reference: a primary key on the scan identifier and a unique key on the
credential and sequence.

## Voter

A voter of the application grants `PhilCheckinBundle::SCAN` (`PHIL_CHECKIN_SCAN`) on a `CheckpointId`: who staffs
which door. See [Installation](installation.md#the-security).

## Readers

A package that reads codes another way — a camera, NFC — dispatches `phil-checkin:code` with `{code}` on the desk
controller's element; the controller sends it, or decides it offline, as a typed code. See [JavaScript](javascript.md).

## Codes the manifest cannot hold

Offline, a code the manifest does not hold is offered to the page by `phil-checkin:lookup`; a package that recognises
it — a code that carries its credential, signed — answers with the entry it stands for. See
[JavaScript](javascript.md#answering-phil-checkinlookup).

## Reactions

The controller dispatches `phil--checkin-bundle--checkin-desk:checked` after each scan, for the page or a package to
react. On the server, every scan the endpoints record — online, or from a journal synchronised — is dispatched as
`Phil\CheckinBundle\Application\Event\CheckInRecorded` to the application's event dispatcher, when it has one:

```php
#[AsEventListener]
final readonly class HandOutBadge
{
    public function __invoke(CheckInRecorded $scan): void
    {
        if ($scan->changesAttendance() && Direction::In === $scan->direction) {
            $this->badges->queue($scan->credentialId, $scan->checkpoint->value);
        }
    }
}
```

The event carries identifiers only — scan, credential, checkpoint, device —, the direction, the outcome, whether it
was decided offline and whether the server decided otherwise. Replays and duplicates are not dispatched again. A slow
reaction goes through Messenger, with the identifiers as the message.

## Packages built on the bundle

The bundle is the core other packages extend without changing it. Two are planned:

| Package | What it brings | Through |
|---|---|---|
| `phil/qr-attendance` | Ticket codes signed with Ed25519, valid offline without a manifest entry; a short-lived rotating code shown in a room, which attendees scan | A `CredentialResolver` that verifies a signed code; a listener of `phil-checkin:lookup` that verifies it in the browser; its catalog finding signed credentials by identifier; for the room code, its own endpoint on the `CheckInLedger` and `CheckpointDesks` |
| A check-in for live-action role-playing games | Characters, waivers and packets handed out at the door | `CredentialAttributes` answering the waiver and the character; a rule such as `attributes['waiver'] == true`; a listener of `CheckInRecorded` that prepares the packet; a reader dispatching `phil-checkin:code` |

Both stay outside the bundle: the bundle names neither, and what they need from it is listed above.
