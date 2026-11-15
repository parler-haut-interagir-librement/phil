# Campaigns

A `Campaign` is one message to a list, or to a segment of it: a subject, a preheader — the line mail clients show
after the subject —, a body in Markdown, the template that lays it out, the sender profile it goes out with, and how
its clicks are counted. It is edited while it is a draft, scheduled or sent now, and sent once.

```php
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Domain\Model\Campaign;

$campaign = new Campaign($id, $site, $list->id, 'November news', $markdown, preheader: 'Three new games');
$campaignRepository->save($campaign);

$campaigns->sendNow($campaign);
$campaigns->schedule($campaign, new DateTimeImmutable('2026-11-20 09:00'));
$campaigns->cancel($campaign);
$campaigns->results($campaign->id);   // deliveries by state, clicks by link
```

## The body and its layout

The body is Markdown, compiled and purified by `phil/markup` at its `all` level: no script, no event attribute, nothing
a message should not hold. It is then laid out:

- by the house layout, `@PhilNewsletter/email/layout.html.twig`: one column, readable without images, its styles
  inline, the links to the preference center and to unsubscribe at the bottom;
- or by a template the campaign names, written by an administrator or a member, rendered in the sandbox.

A layout reads `subject`, `preheader`, `content` — the body, already purified —, `list_name`, `site`,
`unsubscribe_url` and `preferences_url`. The text part of the message is drawn from its HTML.

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

Sending a campaign sends a `SendCampaign` message. Its handler asks the `SegmentResolver` for the recipients — every
subscriber of the list who may receive, read in pages of 500 — and creates one delivery per recipient, whose id is
drawn from the campaign and the subscriber, so that handling the message again queues nothing twice; each new delivery
gets a `SendDelivery` message. The schedule `newsletter` starts the scheduled campaigns every minute.

Each `SendDelivery` checks the subscriber again: one who left the list, paused, bounced or complained since is
skipped, and so is everyone when the campaign was cancelled. Outside the profile's send window, the message is sent
again with a delay up to the next opening; past the profile's pace for the recipient's domain, or its daily ceiling,
after the wait the rate limiter gives. A transport failure leaves the delivery to retry and is thrown, for Messenger's
retries. A paused subscriber whose pause is over receives again.

## Sender profiles

A profile is who a message comes from and how it goes out: the sender, the reply address, the one Mailer transport it
uses — chosen with the `X-Transport` header; there is no rotation between transports —, its send window, its daily
ceiling and its pace per recipient domain, counted for each domain on its own on Symfony's rate limiter, in a cache
pool the workers share. A list names its profile; a campaign may name another; a template sent on its own goes out
with the default profile.

Each message carries the profile's sender, `List-Unsubscribe` and `List-Unsubscribe-Post`, `List-Id`,
`Precedence: bulk` for a campaign, the delivery's id as Mailer metadata, which a provider's webhook sends back, and,
with `bounces.verp`, a return path that names the delivery.
