# Statements of reasons

A decision that restricts the author of a content — `Hide`, `Remove` or `Warn` — comes with a **statement of
reasons** addressed to them: what was decided and on what, why, on which grounds, and how to contest it. Its fields
follow those article 17 of the European Digital Services Act asks of a statement of reasons, and they answer the
duty to tell a person the reasons of a decision that concerns them, which privacy laws such as Québec's Law 25 and
the GDPR also set. The statement never names the moderator nor the member who reported.

## What a moderator gives

The decision form asks for the grounds when the decision restricts the author:

| Field | What it is |
|---|---|
| Reason | One of the configured reasons, the same catalogue the report form offers |
| Facts | What the decision rests on, up to 2,000 characters, required |
| Provision | The clause of the terms, or the law for illegal content, up to 255 characters; the configured one when empty |

In PHP, the grounds are the last argument of `Decisions::decide()`:

```php
use Phil\ModerationBundle\Application\Grounds;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

$decisions->decide($target, DecisionAction::Hide, 'Advertising', grounds: new Grounds('spam', 'The post advertises a shop.'));
```

**The grounds are checked before the action is applied**: a decision without them, with a reason out of the catalogue,
without facts, or resting on the law without naming it, is refused with `InvalidModeration`, and nothing changes —
the content stays as it is, the reports stay open, no event is dispatched.

No statement follows a decision that restricts nobody: `Dismiss`, `Approve` and `Restore`, or a content whose package
names no author. A temporary hiding by a trusted member is reviewed by the moderation, and the decision that confirms
it comes with the statement.

## What the statement says

| Field | Where it comes from |
|---|---|
| The decision and its reach | The action: hidden from everyone but the author and the moderation until restored, removed from the whole site, or a warning |
| The content | Its type, as the target's package registered it |
| The reason | The code the moderator picked, translated as `reason.<code>` |
| The grounds | `terms` or `illegal`, from the configuration of the reason, and the provision |
| The facts | What the moderator wrote |
| A report or the moderation's own initiative | Whether a report from a member was open on the content |
| Automated detection | Whether the watched words had flagged or held the content |
| Who decided | Always a person: the bundle never decides by automated means |
| The ways to contest it | The complaint page, the terms, the out-of-court bodies of the configuration, and the courts |

## Grounds by reason

```yaml
phil_moderation:
    statements:
        grounds:
            spam: { ground: terms, reference: 'Terms of use, section 4' }
            hate: { ground: illegal, reference: 'Criminal Code, s. 319' }
        terms_url: /terms
        complaint_url: /contact/moderation
        out_of_court:
            - { name: 'A certified dispute settlement body', url: 'https://settlement.example' }
```

A reason not listed rests on the terms, with no provision; `illegal` rests on the law, and a restriction for illegal
content names the law it rests on, from the configuration or from the moderator. The addresses are paths of the site
or `https` addresses; any other scheme is refused when the container is built.

## Where the member reads it

| Page | Route | What it does |
|---|---|---|
| Their statements | `phil_moderation_statements` | The statements addressed to the current member, the latest first |
| A statement | `phil_moderation_statement` | One statement, for the member it is addressed to |

Import them behind the firewall that knows the members:

```yaml
phil_moderation_statements:
    resource: '@PhilModerationBundle/config/routes/statement.php'
    prefix: /moderation/statements
```

A visitor meets the firewall, as with any denied page. **A statement addressed to someone else is not found.** The
pages are `private, no-store`.

## Telling the member

`StatementIssued` is dispatched after `DecisionTaken`, carrying the statement. When `phil/notifications-bundle` is
registered and `statements.notification_type` names a type the application declared, the bundle sends that
notification to the member: its subject is `moderation-statement` and the statement's identifier, never its content,
and it links to the statement's page.

```yaml
phil_notifications:
    types:
        moderation.statement:
            channels: [site, email]

phil_moderation:
    statements:
        notification_type: moderation.statement
```

A member identifier the notifications refuse gets no notification; the statement stays on the member's page. Without
`phil/notifications-bundle`, a listener of `StatementIssued` tells the member another way, carrying the identifier of
the statement only.

## Turning them off

`statements: false` leaves the decisions without statements: the form asks for no grounds, and the pages of the
statements are not registered. The table stays in the schema, so that turning them on again needs no migration.

## Appeal

A member contests a decision through the complaint page the configuration names. An appeal reviewed inside the
bundle, by another role than the one that decided, is not started.
