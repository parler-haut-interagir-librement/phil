# CSRF

## What the bundle checks

One request of the bundle carries a CSRF token: the deletion of a quarantined file on the
[administration page](quarantine-and-events.md#the-administration-page), `POST /{id}/delete`, with the token
`phil_upload_scan_quarantine` (`QuarantineAdminController::CSRF_TOKEN_ID`). A deletion without a valid token answers
`400` and deletes nothing.

The bundle declares no upload route of its own and checks no token on uploads: a file reaches `#[CleanFile]` or the
`UploadScanner` through the application's controllers and forms, whose CSRF protection is the application's.

## Why the token is required by default

A deletion is final, and the page is opened by an operator who is signed in: without a token, a page of another site
could make that operator's browser post a deletion. So once Twig and `symfony/security-csrf` are installed, the bundle
registers the page and checks the token, with nothing to turn on. It is the safe default.

## Stateless, without a session

The token is one of FrameworkBundle's stateless ones: the bundle adds `phil_upload_scan_quarantine` to
`framework.csrf_protection.stateless_token_ids`. Symfony's token manager then accepts a deletion when the request's
`Origin` (or its `Referer`) names the application, or when the double-submit cookie matches; it keeps nothing in a
session. A page of another site sends its own origin and is refused.

## Sending the token

The bundle's template sends it in a hidden field of each deletion form:

```twig
<form method="post" action="{{ path('phil_upload_scan_quarantine_delete', {id: file.id}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('phil_upload_scan_quarantine') }}">
    <button type="submit">Delete for good</button>
</form>
```

A template that overrides it, under `templates/bundles/PhilUploadScanBundle/`, keeps that field. A page of the
application that deletes with `fetch` sends the field in the body; the browser adds the `Origin` header itself:

```js
const body = new FormData();
body.append('_token', tokenField.value); // rendered with csrf_token('phil_upload_scan_quarantine')

await fetch(deleteUrl, { method: 'POST', body, credentials: 'same-origin' });
```

With `XMLHttpRequest`, the same `FormData` goes to `send()`. A field of the `FileInput` component of `phil/ux-kit`
is part of the application's own form: the token that form needs is the one the application gives it, as for any
other field.

## When the component was installed for another reason

An application may install `symfony/security-csrf` for its own forms and never import the administration's routes.
With Twig installed, the bundle registers the page anyway, and the page needs Symfony's token manager:

- With `framework.csrf_protection` on, the default once the component is installed, nothing else is needed: the token
  is stateless, so no session is started for it, and the page answers nothing until its routes are imported.
- With `framework.csrf_protection: false`, the container does not build, and the message names the two ways out.

The two ways out:

```yaml
# config/packages/framework.yaml — turn the protection on
framework:
    csrf_protection: true
```

```yaml
# config/packages/phil_upload_scan.yaml — leave the administration, and its check, out
phil_upload_scan:
    admin:
        enabled: false
```

`admin.enabled: false` removes the page and its controller altogether; it never keeps a deletion without a check.
