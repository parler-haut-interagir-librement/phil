# phil/contact-form-bundle

**A "write to us" page for Symfony, protected from spam without a captcha.**

phil/contact-form-bundle gives a contact page: a form with a name, an email, a subject and a message. A valid message
goes by Symfony Mailer to the configured recipients, then the page redirects to itself and says the message left, so
reloading sends nothing twice. Labels, messages and the email are in English and French. The bundle does not keep the
messages, though an application that sends its email through an asynchronous Messenger transport keeps them in its
queue until they leave, as [Mailing](doc/mailing.md) explains. It does not show a captcha. It sends no copy to the
visitor, since a copy would let anyone send a text of their choice to any address through the site.

It is for any Symfony site that wants visitors to write to it without exposing an email address. The form carries the
protection of `phil/spam-protection-bundle`, which calls no third party. The email leaves from the site's own address,
because a visitor's address in `From` fails the domain's SPF and DMARC checks and lands in spam. The visitor is in
`Reply-To`, so answering answers them.

The page works as it comes, with a route and a template an application replaces to fit its layout. For a page of its
own design, an application uses `ContactFormType` and `ContactMailer` in its own controller. To write to someone other
than the site, such as an organisation of a directory, it replaces the `Recipients` port with its own service.

## Status

| Part | State |
|---|---|
| `ContactFormType` and `ContactMessage`, protected and checked | Implemented and tested |
| `ContactMailer`: from the site, `Reply-To` the visitor, headers safe | Implemented and tested |
| `ContactFormController` and `config/routes.php` | Implemented and tested |
| The `Recipients` port, to write to someone else than the site | Implemented and tested |
| Templates and translations, English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the route. |
| [Configuration](doc/configuration.md) | Every key, and the services. |
| [The page](doc/page.md) | The form, what is checked, the responses, the templates. |
| [Mailing](doc/mailing.md) | What the email holds, and why. |
| [Extension points](doc/extension-points.md) | `Recipients`, and using the form elsewhere. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/contact-form-bundle
```

```yaml
# config/packages/phil_contact_form.yaml
phil_contact_form:
    sender: contact@example.com
    recipients: [info@example.com]

# config/routes/phil_contact_form.yaml
phil_contact_form:
    resource: '@PhilContactFormBundle/config/routes.php'
    prefix: /contact
```

## Requirements

PHP 8.5 or later with `ext-filter`; `phil/spam-protection-bundle` 0.1; Symfony 8.2's form, HTTP foundation, mailer, MIME, validator,
Twig bridge and dependency injection; Twig 3; a PSR-3 logger.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
