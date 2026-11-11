# Rules

A rule is the application's answer to a message no standard describes: a pattern on one field of the message, the kind
of bounce it means, and the action it leads to.

```php
use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Rule\RuleScope;

$rules = new Rules(
    Rule::containing('quota', Field::Body, 'over quota', BounceKind::Soft),
    Rule::matching('gone', Field::Body, '/account (has been )?(disabled|closed)/i', BounceKind::Hard, BounceAction::Suppress),
    Rule::containing('ticket', Field::Header, 'helpdesk', BounceKind::AutoReply, header: 'X-Mailer'),
    Rule::containing('policy is gone', Field::Diagnostic, 'user does not exist', BounceKind::Hard, scope: RuleScope::Always),
);
```

## Declaring

| Constructor | Pattern |
|---|---|
| `Rule::matching($name, $field, $pattern, $kind, $action, $scope, $header)` | A PCRE regular expression with its delimiters and flags |
| `Rule::containing($name, $field, $text, $kind, $action, $scope, $header)` | A text the field contains, without regard to case, in UTF-8 |

The action defaults to the action of the kind (`BounceAction::for()`). A pattern that is not a valid regular
expression, an empty text, or a rule on `Field::Header` without a field name throws `Exception\InvalidRule` when it is
declared, not when a message arrives. A pattern that fails on a message — too much backtracking, broken UTF-8 — does
not match it.

## Fields

| `Field` | The text |
|---|---|
| `Subject` | The subject, its encoded words decoded |
| `From` | The `From` field as written |
| `Body` | The text parts of the message, not those of the message it returns, one after the other, up to the limit of text |
| `Diagnostic` | The diagnostics of the recipients of a delivery status notification, one a line |
| `Header` | Every value of the named field, one a line |

## Scopes

| `RuleScope` | The rule is tried on |
|---|---|
| `Unrecognised` (default) | Messages whose kind is unknown: unrecognised, a bounce written as text without a code, a notification without a recipient |
| `Always` | Every message, so that it may correct what the parser decided |

## Order

The rules are tried in their order, and the first that matches decides: the report keeps its format and recipients,
and takes the rule's kind, action and name. `Rules::with($rule)` adds a rule at the end; `with($rule, before: 'name')`
and `with($rule, after: 'name')` place it next to a rule by name — `before` wins when both are given. Nothing is
ordered by number. Two rules of one name, or a name to place by that does not exist, throw `Exception\InvalidRule`.

## Writing a rule from a message

A message left for review holds the words a rule needs: the server's sentence in its text, its subject, or a field it
adds. Write the narrowest text that names the case, try the rule against the messages kept for review, and keep the
rules few: each costs a match on every message of its scope.
