# Policies and decisions

## A policy

An `AccessPolicy` says what a content asks of its reader. It is immutable: the `with…()` methods return a new policy.

| Member | Values | Default |
|---|---|---|
| `audience` | `Audience::Public`, `Audience::Members`, `Audience::Restricted` | — |
| `rule` | a `Phil\Rules\Rule`, for a restricted audience only | `null` |
| `publicFrom` | the instant from which the content is public for good | `null` |
| `preview` | whether a viewer without the right reads the part before the cut | `false` |
| `whenDenied` | `WhenDenied::Locked`, a locked entry, or `WhenDenied::Hidden`, nothing at all | `Locked` |

```php
AccessPolicy::public();
AccessPolicy::members()->withPreview();
AccessPolicy::restricted($rule)->withPublicFrom(new DateTimeImmutable('2027-01-15T00:00:00+00:00'));
AccessPolicy::restricted($rule)->withWhenDenied(WhenDenied::Hidden);
```

A contradiction is refused with `InvalidPolicy` when the policy is built: a restricted content without a rule, a rule
on any other audience, a public content with an early access, a preview or a hidden denial, and a hidden content with a
preview.

### Early access

With `publicFrom`, the audience applies until that instant; from it on, everyone reads the content, and the policy
never closes again. Supporters read an episode a week before the others: the episode is restricted to `tier:supporter`
and public a week after its release.

`isPublicAt($instant)` tells whether everyone reads the content at an instant.

## A decision

`AccessDecider::decide($policy, $viewer, $facts = [])` answers at the clock's instant:

| Case | `access` | `reason` | `personal` |
|---|---|---|---|
| A public content | `Full` | `Reason::Public` | no |
| An early access that ended | `Full` | `Reason::EarlyAccessEnded` | no |
| For members, a signed-in viewer | `Full` | `Reason::Member` | yes |
| For members, a visitor | `Preview`, `Locked` or `Hidden` | `Reason::Anonymous` | yes |
| Restricted, the rule holds | `Full` | `Reason::RulePassed` | yes |
| Restricted, the rule fails | `Preview`, `Locked` or `Hidden` | `Reason::RuleFailed` | yes |

A denied viewer gets `Hidden` when the policy hides the content, `Preview` when it shows a preview, and `Locked`
otherwise.

A public content, or one past its early access, is decided without reading the viewer. A content for members asks only
whether the viewer is signed in. A restricted content evaluates its rule on the facts of the viewer — see
[conditions](conditions.md) — and the grants are read from their source only when a condition asks for a feature.

`isGranted()` tells whether the viewer reads the whole content, and `isVisible()` whether the content appears to them
at all.

### What remains

`explanation` holds the leaves of the rule that decided, each a `Phil\Rules\Evaluation\Step` with its JSON Pointer, its
reason code and its details. A locked entry shows what remains from them: `access.feature-not-yet` gives the instant a
content opens in `opens_at`, `access.anonymous` asks the viewer to sign in, `access.feature-missing` names the feature
to subscribe to. A hidden decision carries no explanation.

### Hidden contents

A hidden content does not appear in a list, and its address is answered as the address of a content that does not
exist: same status, same body, same headers. A response that tells a hidden content from a missing one would tell a
visitor that the content exists.

### Caching

- A decision whose `personal` is false is the same for everyone: the page may be stored in a shared cache.
- A personal decision is never stored in a shared cache; a page built from one varies by viewer.
- `changesAt` is the end of an early access still to come: the decision changes for everyone at that instant, and no
  cache keeps the page past it.

## The cut of a document

A document in UJO (Universal JSON Output) marks the end of its public part with a block of its own type, `access-cut` by
default. `PreviewCut` gives the blocks a decision lets the viewer read:

| `access` | Blocks |
|---|---|
| `Full` | every block but the cuts |
| `Preview` | the blocks before the first cut |
| `Locked`, `Hidden` | none |

```php
$cut = new PreviewCut();                // or new PreviewCut('paywall')

$blocks = $cut->visibleBlocks($decision, $document['blocks']);
$cut->publicPart($document['blocks']);  // the blocks before the first cut
$cut->hasCut($document['blocks']);
```

A document without a cut has no public part: its preview is empty, never the whole content. The reserved blocks are
not hidden in the page by CSS or JavaScript; they are not sent.

## Structured data

Search engines read a reserved content as an anonymous visitor does. `StructuredData::of($policy, $instant, '.reserved')`
gives the schema.org properties to merge into the content's JSON-LD:

```php
['isAccessibleForFree' => false, 'hasPart' => [['@type' => 'WebPageElement', 'isAccessibleForFree' => false, 'cssSelector' => '.reserved']]]
```

or `['isAccessibleForFree' => true]` when the content is public at that instant. The properties follow the policy,
never the current viewer, so that the markup is the same for everyone. The selector names the element that holds the
reserved part; an empty selector, one longer than 255 bytes, or one holding a control character or a `<` is refused.
