# Campaigns

A `Campaign` is one message to a list, or to a segment of it: a subject, a preheader — the line mail clients show
after the subject —, a body in Markdown, the template that lays it out, the sender profile it goes out with, how its
clicks are counted, and its author. It is edited while it is a draft, approved, scheduled or sent now, and sent once.

```php
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Domain\Model\Campaign;

$campaign = new Campaign($id, $site, $list->id, 'November news', $markdown, preheader: 'Three new games', author: $user->getUserIdentifier());
$campaignRepository->save($campaign);

$campaigns->approve($campaign);       // by the signed-in user of the approving role
$campaigns->sendNow($campaign);
$campaigns->schedule($campaign, new DateTimeImmutable('2026-11-20 09:00'));
$campaigns->cancel($campaign);
$campaigns->results($campaign->id);   // deliveries by state, clicks by link
```

## Approval

With `approval.required`, on by default, a campaign is sent once approved: `sendNow()` refuses a campaign that awaits
its approval with `InvalidNewsletterValue`, and a scheduled campaign whose time has come waits, scheduled, until it is
approved, then starts at the next minute of the schedule.

`Campaigns::approve()` records who approved the campaign and when, as a `CampaignApproval`: the user identifier of the
signed-in user, as Symfony's security gives it, and the instant of the clock. It throws `ApprovalRefused` when the user
lacks the role of `approval.role`, `ROLE_NEWSLETTER_APPROVER` by default, or is not signed in. With
`approval.four_eyes`, the approver must be someone other than the campaign's author: a campaign without an author is
then never approved. `CampaignApproved` is dispatched once the approval is saved.

The approval is of the campaign as it stands. `Campaign::edit()` withdraws it, so that what goes out is what was
approved; `Campaigns::withdrawApproval()` withdraws it on purpose. A draft or a scheduled campaign is approved; a
campaign being sent, sent or cancelled is not.

The author and the approver are user identifiers of the application, at most 191 characters, kept in the campaign's
table; they name members of the team, never subscribers.

## The body and its layout

The body is Markdown, compiled and purified by `phil/markup` at its `all` level: no script, no event attribute, nothing
a message should not hold. It is then laid out:

- by the house layout, `@PhilNewsletter/email/layout.html.twig`: one column, readable without images, its styles
  inline, the links to the preference center and to unsubscribe at the bottom;
- or by a template the campaign names, written by an administrator or a member, rendered in the sandbox.

A layout reads `subject`, `preheader`, `content` — the body, already purified —, `list_name`, `site`,
`unsubscribe_url` and `preferences_url`.

The text part of the message is drawn from its HTML by `TextPart`, which writes the address of every web link between
angle brackets after its words: a reader of the text part still finds the links to unsubscribe, to the preference center
and, in a confirmation, to confirm. The head, the styles and what is hidden, the preheader among them, are left out.
`ContentRenderer` takes another Mime `HtmlToTextConverterInterface` as its last argument; it returns decoded text.

## Templates of administrators and members

A `Template` is a subject and a Twig source, for one site or, without a site, shared by all. It lays out campaigns, is
sent on its own by an automation, or replaces the confirmation of a double opt-in under the key `confirmation`.

It is never rendered by the application's Twig. `SandboxedTemplates` renders it in a `Twig\Sandbox\Sandbox`, on an
environment of its own with nothing registered, under a strict `SecurityPolicy`:

| Allowed | |
|---|---|
| Tags | `apply`, `for`, `if`, `set`, `with` |
| Filters | `abs`, `capitalize`, `date`, `default`, `e`, `escape`, `first`, `join`, `last`, `length`, `lower`, `nl2br`, `number_format`, `replace`, `round`, `slice`, `striptags`, `title`, `trim`, `upper`, `url_encode` |
| Functions, methods, properties | none |

The context holds strings, numbers, lists and the purified body. The range operator `1..1000000` is refused when a
template compiles, so that no template loops more times than its data has items; the output is cut at a megabyte; a
source holds at most 64 KiB. A template that breaks the policy throws `InvalidNewsletterValue`; `check()` tells an
editor before the template is saved. The compiled templates are kept for the next message, up to 64, and forgotten on
`reset()`.

## The Markdown editor

`@PhilNewsletter/editor/markdown.html.twig` is a text area with a toolbar — heading, bold, italic, link, list, quote
— and a preview, by the Stimulus controller `phil--newsletter-bundle--markdown-editor`:

```twig
{{ include('@PhilNewsletter/editor/markdown.html.twig', {name: 'body', value: campaign.body, preview_url: path('phil_newsletter_preview')}) }}
```

The preview posts the text to `phil_newsletter_preview`, which answers the configured role only, and shows the message
laid out in a frame sandboxed without scripts, behind a `Content-Security-Policy: sandbox`. Without JavaScript, the
text area is still the field the form sends.

## Sending

Sending a campaign starts a send run and sends a `SendCampaign` message that names the campaign and the run. Its
handler asks the `SegmentResolver` for the recipients — every subscriber of the list who may receive, read in pages of
500 — and creates one delivery per recipient, under the run, whose id is drawn from the campaign and the subscriber, so
that handling the message again queues nothing twice and no later run of the campaign sends twice to one recipient;
each new delivery gets a `SendDelivery` message. Once they are all queued, the run keeps how many it holds. The
schedule `newsletter` starts the scheduled campaigns every minute.

Each `SendDelivery` checks the subscriber again: one who left the list, paused, bounced or complained since is
skipped, and so is everyone when the campaign was cancelled. Outside the profile's send window, the message is sent
again with a delay up to the next opening; past the profile's pace for the recipient's domain, or its daily ceiling,
after the wait the rate limiter gives. A transport failure leaves the delivery to retry and is thrown, for Messenger's
retries. A paused subscriber whose pause is over receives again.

## Send runs and the circuit breaker

A `SendRun` is one attempt at sending a campaign: its number, from 1, and an id drawn from the campaign and that
number; its state, `running`, `paused`, `completed` or `cancelled`; how many messages it queued; what became of them —
`sent`, `failed` (each attempt the transport refused; a message sent after a retry counts in both), `skipped`,
`bounced` and `complained` —; and when it started, finished queuing, was paused and ended. Each worker adds to these
counts in the store, in one statement, and a state changes only from the state it was read in, so that the workers of
one run lose nothing to each other. Messages carry identifiers only: the delivery, the campaign, the run.

```php
use Phil\NewsletterBundle\Application\SendRuns;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;

foreach ($sendRuns->ofCampaign($campaign->id) as $run) {
    $run->status;                           // SendRunStatus
    $run->queued;
    $run->count(SendRunCounter::Sent);
    $run->pauseReason;                      // PauseReason, while it is paused
}

$sendRuns->pause($runId);                   // an operator holds it
$sendRuns->resume($runId);                  // an operator sends again what it holds
$sendRuns->cancel($runId);                  // what it had not sent is skipped
```

A run is completed once every message it queued was sent or skipped; `SendRunCompleted` is dispatched.

**The circuit breaker.** After each message counted, the run is read back: once at least `circuit_breaker.minimum`
messages were tried since it started or was last resumed, it is paused when the share of messages sent reported as spam
passes `complaint_rate`, the share that bounced passes `bounce_rate`, or the share of attempts the transport refused
passes `transport_failure_rate` — read in that order, in percent. `SendRunPaused` is dispatched with the
`PauseReason`, for the application to warn an operator. The bounces and complaints of a delivery come from the bounce
mailbox or the provider's webhook, and each is counted once.

A paused run sends nothing: each of its messages a worker receives is left queued. Nothing resumes it but an operator:
`SendRuns::resume()` queues again what the run holds, queued or failed, in pages of 500, dispatches `SendRunResumed`,
and the breaker counts from there. There is no automatic resumption, no ramp of the pace after it, and no test of a
part of the audience before the rest: an application that wants them builds them on these services. `SendRuns::pause()`
holds a running run for an operator, with the reason `operator`; `cancel()` ends a running or paused run and skips
what it had not sent.

`circuit_breaker: false` turns the breaker off; the runs still count, complete and answer an operator.

## Sender profiles

A profile is who a message comes from and how it goes out: the sender, the reply address, the one Mailer transport it
uses — chosen with the `X-Transport` header; there is no rotation between transports —, its send window, its daily
ceiling and its pace per recipient domain, counted for each domain on its own on Symfony's rate limiter, in a cache
pool the workers share. A list names its profile; a campaign may name another; a template sent on its own goes out
with the default profile.

Each message carries the profile's sender, `List-Unsubscribe` and `List-Unsubscribe-Post`, `List-Id`,
`Precedence: bulk` for a campaign, the delivery's id as Mailer metadata, which a provider's webhook sends back, and,
with `bounces.verp`, a return path that names the delivery.

## Upgrading to 0.3

- **Approval is required by default.** A draft or scheduled campaign of 0.2 is no longer sent, nor started at its
  time, until a user of `approval.role` approves it. Give that role to the people who approve, or set
  `approval.required: false` to send as before.
- **The tables gain a table, columns and an index.** Run the migration [Upgrading from 0.2](storage.md#upgrading-from-02)
  describes before the workers of 0.3 start.
- `Campaigns` takes the `SendRuns` as its sixth argument, then an optional `ApprovalPolicy`, the authorization checker,
  the token storage and the event dispatcher. `SendCampaignHandler` takes the `SendRuns` and whether approval is
  required; `SendDeliveryHandler` and `BounceHandler` take the `SendRuns` as their last, optional argument. The bundle
  wires them all; only an application that builds them itself changes its calls.
- `Campaign::start()` takes whether approval is required, `true` by default; `Campaign::restore()` takes the
  approval as its last, optional argument; the constructors of `Campaign` and `Delivery` take the author and the send
  run as their last, optional arguments.
- `SendCampaign` names the send run as its second, optional argument. A message queued by 0.2, without one, is handled
  under the campaign's run still queuing, or a new one.
- `DeliveryRepository` declares `countOfRun()`, `pendingOfRun()` and `skipPendingOfRun()`, and `SendRunRepository` is
  a new port: a repository of the application's own implements them.
