# Security

The templates are written by people the application trusts with its emails, not with its server. The bundle keeps what
they write inside the email.

## The sandbox

- **An allow-list in strict mode.** Every source renders with Twig's `Sandbox` and a strict `SecurityPolicy`: a tag,
  filter, function or test that is not listed is refused, and the only methods are `format()` and `getTimestamp()` of a
  date. Nothing is included, extended, imported or read from a file; `constant`, `attribute`, `source`, `range` and the
  arrow functions of `map`, `filter` and `reduce` are out of reach.
- **An environment of its own.** The sandbox does not see the application's Twig, its globals, its extensions or its
  templates; the only templates it can load are the sources it is given, by the hash of their content.
- **Bounded work.** Loops nest at most two deep over lists of at most 1000 values, and every output stops at
  `max_html_bytes`. The values are checked against the schema before the render: their types, the length of a text,
  the size of a list.
- **Escaping.** A body is escaped as HTML, `raw` is refused, and the components escape every text and attribute they
  write. A Markdown body is purified by `phil/markup`.
- **Addresses.** A button links to an `http`, `https` or `mailto` address only, an image to an embedded image or an
  `https` address only, each read by `phil/url` or `phil/email-address`; `javascript:` and relative addresses are
  refused.
- **Checked before saved, and again before published.** A template is compiled, checked against the policy and
  rendered in every variant before its draft is saved and before it is published, so a template that would fail when
  sent is refused when written. The sandbox applies at every render too: a template written around the bundle — in the
  tables of the contents, or published through `phil/content-bundle` directly — renders under the same policy and the
  same limits, and a stored row that holds no readable template is refused.
- **Nothing sent unpublished.** A draft renders only in the preview and in `phil:email-templates:render --draft`; the
  emails sent read published versions only.

## The preview

The preview frame is an `<iframe sandbox="">` — scripts, forms, pop-ups and navigation off, an opaque origin — and is
served with a policy that loads nothing but images and inline styles, runs no script and posts no form, `nosniff`,
`no-referrer` and `no-store`. Every page asks the configured role; every form carries a stateless CSRF token checked
against the origin of the request.

## Personal data

The messages carry identifiers only, the values coming back through the application's `DeliveryResolver` when the
message is handled; the events and the audit trail carry identifiers only; a violation of the schema names a path,
never a value; an author that is not an identifier — an email address — is not kept. The sample values of the schemas
are fiction: keep them so.

## What is left to the application

- The role of the administration, and who holds it; and the role of the administration of `phil/content-bundle`, which
  lists the roots of the templates among the contents.
- The layout, which is trusted code of the application, and the Twig extensions it gives the sandbox.
- The images under `embeds.directory`: anything in it may be embedded.
- Rate and recipients of what it sends.
