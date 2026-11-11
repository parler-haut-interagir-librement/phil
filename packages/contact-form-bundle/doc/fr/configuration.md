<!-- phil-dev source-sha256=9c28f70417997c80d6820cf9b0ffcf4d2729b5eed0d6f31ec9294ea86aa906b2 -->
# Configuration

```yaml
phil_contact_form:
    sender: contact@example.com          # required: an address of the site's own domain
    recipients: [info@example.com]       # required: at least one
    subject_prefix: '[Contact] '         # '' by default
    template: '@PhilContactForm/contact.html.twig'
```

| Clé | Signification |
|---|---|
| `sender` | Le `From` de chaque message : une adresse depuis laquelle le domaine du site peut envoyer, sinon SPF et DMARC échouent |
| `recipients` | Ceux qui reçoivent les messages, par `ConfiguredRecipients` |
| `subject_prefix` | Le début de chaque sujet, pour trier les messages |
| `template` | Le gabarit de la page, qui reçoit `form`, `sent` et `failed` |

| Service | Rôle |
|---|---|
| `ConfiguredRecipients`, avec l'alias `Recipients` | Les destinataires de la configuration |
| `ContactFormType` | Le formulaire, avec l'étiquette `form.type` |
| `ContactMailer` | Le courriel |
| `ContactFormController` | La page, public, avec l'étiquette `controller.service_arguments` |
