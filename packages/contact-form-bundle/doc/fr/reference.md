<!-- phil-dev source-sha256=371dfb10c9744634feb64e89140253b3efeee2547e04df31cf8d1805fa323c88 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `PhilContactFormBundle` | `final class`, `AbstractBundle` | Alias `phil_contact_form` ; les services ; la protection contre le pourriel requise |
| `Application\ContactMessage` | `final class` | Ce qu'écrit un visiteur, avec ses vérifications |
| `Application\Recipients` | interface | Ceux qui reçoivent un message |
| `Application\ConfiguredRecipients` | `final readonly class`, `Recipients` | Les destinataires de la configuration |
| `Infrastructure\Form\ContactFormType` | type de formulaire | Le formulaire, protégé |
| `Infrastructure\Mailer\ContactMailer` | `final readonly class` | Le courriel |
| `Infrastructure\Http\ContactFormController` | contrôleur | La page |
| `config/routes.php` | routes | `phil_contact_form`, `GET` et `POST` |
| `templates/contact.html.twig`, `_form.html.twig`, `email/message.*.twig` | gabarits | La page, le formulaire, le courriel |
