# Rendering and sending

## Rendering without sending

`Application\Rendering\EmailRenderer` renders an email and sends nothing:

```php
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;

$email = $renderer->render('confirmation', $values, new RenderOptions(
    site: null,              // the current site by default
    locale: 'fr_CA',         // the default locale by default
    segments: ['first-time'],
    version: null,           // a version the site published, rather than the one it sends
));

$email->subject; $email->preheader; $email->html; $email->text; $email->embeds;
$email->locale;  // fr: the locale the template was found in
$email->origin;  // TemplateOrigin::Site, Shared or Default
$email->version; // the version, null for a default file
$email->isClippedByGmail(); // the HTML is larger than 102 KiB
```

It finds the template ([Sites, drafts and versions](sites-and-versions.md#which-template-is-sent)), a published
version or a default file, never a draft, checks the values against
its schema — `InvalidVariables` when they do not match —, renders its sources in the sandbox, wraps the body in the
house layout, inlines the styles, writes the plain-text part from the HTML unless the template writes its own, and reads
the embedded images. `RenderingFailed` reports a source that fails at run time, a component given something it refuses,
an output past `max_html_bytes`, or an image that cannot be read; `UnknownTemplate` a template found nowhere.

`renderSamples()` renders a template already found with its sample values, as the preview and the checks do.

## The house layout

The layout is a template of the application's Twig, trusted and not sandboxed: `@PhilEmailTemplates/email/layout.html.twig`
by default, overridden as any bundle template in `templates/bundles/PhilEmailTemplatesBundle/email/layout.html.twig`,
or another named by `layout`. It receives `subject`, `preheader`, `body` (HTML), `email` (key, site, locale,
segments), `theme` (`light`, `dark`, `font`) and `brand` (`name`, `logo`).

The default layout draws a centred column of 600 pixels on the background colour, narrowing on a small screen; the
preheader first, hidden, followed by spacing characters so that the inbox shows nothing of the body after it, and marked
`data-text="skip"` so that the plain text leaves it out; the brand, a name or a logo; then the body.

## The dark mode

The layout declares `color-scheme: light dark` and `supported-color-schemes`, in its `<meta>` and its style sheet, and
restyles the background, the card, the text, the links and each component in a `@media (prefers-color-scheme: dark)`
block, with the colours of `theme.dark`. The clients that apply it — Apple Mail, iOS Mail, Outlook for macOS and some
others — show the dark scheme; those that do not, Gmail's web client among them, keep the light one or invert it on
their own. The rules of the dark mode stay in the `<style>` element, since they cannot be inlined.

## The inlined styles

With `twig/cssinliner-extra`, its `inline_css` filter copies each rule of the layout's `<style>` element onto the
elements it matches, a declaration already inline winning, for the clients that read no style sheet; the media queries
stay in the `<style>` element, which is kept. Without it, `NoStyleInliner` leaves the document as it is: the components
already write their styles inline.

## The plain-text part

`HtmlToText` writes the plain-text part from the whole document, read by the HTML5 parser of PHP as a browser reads it:
paragraphs and headings separated by a blank line, a line break kept, a link followed by its address in brackets, a list
item by a dash or its number, the cells of a data table separated by `|`, those of a layout table — `role="presentation"`
— on lines of their own, an image by its alternative text, a rule by `----`. The head, the styles, the scripts and every
element marked `hidden` or `data-text="skip"` are left out. A template writes its own in `text`.

## Embedded images

The images a template declares in `embeds` are read by the `EmbedLoader`, by default `DirectoryEmbedLoader` under
`embeds.directory`: a path that leaves the directory, through `..` or a symbolic link, is refused, as is a file larger
than `embeds.max_bytes` or one that is not a PNG, a JPEG or a GIF, recognized by its first bytes. Each becomes an
`EmbeddedImage` attached inline under its content identifier, so that `cid:logo` in the HTML shows it.

## An Email ready to send

`Application\EmailComposer` writes a rendered email onto a Symfony Mailer `Email` — subject, HTML, text, the images
attached inline — and gives it the configured `from` when it has no sender:

```php
$mailer->send($composer->compose('ticket', $values, new RenderOptions(locale: 'fr'))->to($address));
$composer->compose('ticket', $values, email: new Email()->from('box-office@example.com'));
EmailComposer::toEmail($renderedEmail);
```

A `TemplatedEmail` is not needed: the body is rendered already, and Mailer's body renderer leaves such an `Email` as
it is.

## Sending from a message

`Infrastructure\Messenger\SendTemplatedEmail` carries a template key, a reference — an order, a ticket, a registration —
and optionally a site: identifiers only, so that no address and no value waits in a queue. When it is handled, the
application's `DeliveryResolver` gives back the `Delivery` — the recipients, the values, the locale, the segments, the
site — and the email is composed and sent through Mailer; a reference it returns `null` for is dropped.

```php
final class Deliveries implements DeliveryResolver
{
    public function resolve(string $template, string $reference): ?Delivery
    {
        $order = $this->orders->find($reference);

        return null === $order ? null : new Delivery([new Address($order->email)], ['order' => $order->toTemplate()], $order->locale);
    }
}
```

```yaml
phil_email_templates:
    messenger: { delivery_resolver: App\Mail\Deliveries }
```

```php
$bus->dispatch(new SendTemplatedEmail('order.confirmation', $order->id));
```

Route the message to an asynchronous transport as any other.
