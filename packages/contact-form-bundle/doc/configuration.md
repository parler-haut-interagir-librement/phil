# Configuration

```yaml
phil_contact_form:
    sender: contact@example.com          # required: an address of the site's own domain
    recipients: [info@example.com]       # required: at least one
    subject_prefix: '[Contact] '         # '' by default
    template: '@PhilContactForm/contact.html.twig'
```

| Key | Means |
|---|---|
| `sender` | The `From` of every message: an address the site's domain may send from, or SPF and DMARC fail |
| `recipients` | Who receives the messages, through `ConfiguredRecipients` |
| `subject_prefix` | What every subject starts with, to sort the messages |
| `template` | The template of the page, given `form`, `sent` and `failed` |

| Service | Role |
|---|---|
| `ConfiguredRecipients`, aliased `Recipients` | The recipients of the configuration |
| `ContactFormType` | The form, tagged `form.type` |
| `ContactMailer` | The email |
| `ContactFormController` | The page, public, tagged `controller.service_arguments` |
