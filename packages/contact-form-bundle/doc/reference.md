# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilContactFormBundle` | `final class`, `AbstractBundle` | Alias `phil_contact_form`; the services; the spam protection required |
| `Application\ContactMessage` | `final class` | What a visitor writes, with its checks |
| `Application\Recipients` | interface | Who receives a message |
| `Application\ConfiguredRecipients` | `final readonly class`, `Recipients` | The configuration's recipients |
| `Infrastructure\Form\ContactFormType` | form type | The form, protected |
| `Infrastructure\Mailer\ContactMailer` | `final readonly class` | The email |
| `Infrastructure\Http\ContactFormController` | controller | The page |
| `config/routes.php` | routes | `phil_contact_form`, `GET` and `POST` |
| `templates/contact.html.twig`, `_form.html.twig`, `email/message.*.twig` | templates | The page, the form, the email |
