# Testing

| Area | What is covered |
|---|---|
| `ContactMailer` | Sender, recipients, `Reply-To`, subjects, headers safe from line breaks, templates, locale |
| `ContactFormController`, `ContactFormType`, `ContactMessage` | The page, a sent message, an incomplete one, a transport that is down, options, every check |
| `PhilContactFormBundle` | The services, the configuration and its refusals, the spam protection required |

A functional suite boots a kernel with FrameworkBundle — a mailer that sends nowhere —, TwigBundle, the spam
protection and the bundle under `/contact`: `ContactFormBundleTest` renders the page in French, submits it as a
browser would, proof of work solved, reads the email sent, and sees a submission without protection held. Installed
on its own, where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/contact-form-bundle/tests
```
