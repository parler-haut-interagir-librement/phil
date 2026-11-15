# Acceptance

## What a member has to accept

For each required policy, in the order of the configuration, a member has to accept the version in force when they
have not accepted the version required or a later one — or when the policy is renewed and their latest such
acceptance is older than its days. `AcceptanceChecker::pending()` gives those versions.

## The gate

`AcceptanceGate` listens to `kernel.request` after the firewall. For a signed-in member with a version to accept:

- a page is answered `303 See Other` to the acceptance page, `?target=` the address they asked for;
- an API is answered `403` with an RFC 9457 problem as `application/problem+json`:

```json
{
    "type": "about:blank",
    "title": "Terms to accept",
    "status": 403,
    "detail": "A version of the terms awaits your acceptance.",
    "accept": "/terms/accept",
    "pending": [{"policy": "terms", "version": 3, "url": "/terms/terms/v3"}]
}
```

A request is an API's when its path is under one of `gate.api_paths`, its format is JSON, or its first acceptable type
is JSON. A visitor, a sub-request, the bundle's own routes (`phil_terms_*`), `gate.exempt_routes` and the paths under
`gate.exempt_paths` pass: a logout route, the assets of the page and a health check belong there.

Each request reads the versions of each policy once, then asks the store one question per required policy, on the
index of the member, the policy and the version.

## The acceptance page

`phil_terms_accept` shows the text of each version to accept, each with its own checkbox, never ticked in advance, and
a permanent link to the version. The form is taken only when every box is ticked for the version shown: a version
published meanwhile is shown again, with a `422`, rather than taken as accepted. Each acceptance is kept with the
channel `form`, then the member goes back to `target` — a path of the site only, `/` for anything else. A visitor is
denied. The form carries a CSRF token of id `phil_terms`; a form without a valid one is refused with a `400`.

## The signup form

```php
$builder = $this->createFormBuilder($member, ['terms_acceptance' => true]);    // every required policy
$builder = $this->createFormBuilder($member, ['terms_acceptance' => ['terms', 'code_of_conduct']]);
```

Each policy with a version in force adds an unmapped checkbox `phil_terms_accept_<policy>`, never ticked in advance,
labelled `signup.accept`, and the number of the version shown in a hidden field `phil_terms_version_<policy>`. The
form is valid only when each box is ticked and the version shown is still the one in force. Once the member exists:

```php
use Phil\TermsBundle\Infrastructure\Form\SignupAcceptances;

if ($form->isSubmitted() && $form->isValid()) {
    $member = $members->register($form->getData());
    $signupAcceptances->record($form, $member->id, $request->getClientIp());
}
```

## From code

```php
use Phil\TermsBundle\Domain\AcceptanceChannel;

$terms->accept($memberId, 'terms', 3, AcceptanceChannel::Api, $request->getClientIp());
```

An acceptance is of the version in force or of a later one already published; a version a later one replaced is
refused with the reason `superseded_version`. `Terms` dispatches `TermsAccepted` — identifiers only — on the event
dispatcher.
