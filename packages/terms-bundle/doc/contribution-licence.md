# Contribution licence

A site where members publish content (texts, images, rules, reviews) says under which licence that content is
published. The bundle keeps that licence with the terms: one policy names it, each of its versions chooses it, and the
member's acceptance of a version dates it.

## Declare the policy

```yaml
phil_terms:
    policies:
        terms: ~
        contributions: { required: true, contribution_licence: true }
```

One policy at most names the licence: the build stops on a second one. Making it required has the gate ask every
signed-in member to accept it before they go on, so that no contribution is made without a licence.

## Publish a version

Each version of that policy names its licence by SPDX identifier, such as `CC-BY-SA-4.0`, `CC0-1.0` or
`LicenseRef-site` for a licence of the site's own: one identifier of 64 characters at most, never an expression such
as `MIT OR Apache-2.0`.

```php
$terms->publish('contributions', ['en' => $charter, 'fr' => $charte], licence: 'CC-BY-SA-4.0');
```

The administration shows a licence field for that policy alone. A version of that policy without a licence is refused
with the reason `licence_required`, a licence on any other policy with `licence_not_expected`, and a value that is not
an identifier with `licence`. The licence is part of the version, so it never changes once published: a new licence
is a new version, material or not.

The public page of a version and the acceptance page show the licence under the text.

## Find the licence of a contribution

`ContributionLicences::at()` answers under which licence a member published something at a moment:

```php
use Phil\TermsBundle\Application\ContributionLicences;

$licence = $licences->at($member, $post->publishedAt);

$licence?->licence;     // "CC-BY-SA-4.0"
$licence?->version;     // 2, the version of the policy the member accepted
$licence?->acceptedAt;  // when they accepted it
```

It takes the highest version the member had accepted by that moment among those in force at that moment. A later
version never changes the licence of an earlier contribution, and a version accepted before it takes effect only
applies from then. The answer is `null` when the member had accepted no version naming a licence, or when no policy
names one.

The licence is read from the acceptances, so the erasure of a member's acceptances by `phil/privacy-bundle` erases
this record too: an application that must keep the licence of the content it keeps stores it with the content when
the content is published.
