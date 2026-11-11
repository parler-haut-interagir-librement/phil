# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-filter` | any | The configuration's addresses checked |
| `phil/spam-protection-bundle` | ^0.1@dev | The form's protection |
| `symfony/form`, `symfony/options-resolver`, `symfony/validator` | ^8.2 | The form and its checks |
| `symfony/mailer`, `symfony/mime`, `symfony/twig-bridge`, `twig/twig` | ^8.2, ^3.21 | The email and the page |
| `symfony/http-foundation`, `symfony/dependency-injection`, `symfony/config` | ^8.2 | The controller and the bundle |
| `psr/log` | ^3.0 | A message the transport refuses is logged |

```sh
composer require phil/contact-form-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\SpamProtectionBundle\PhilSpamProtectionBundle::class => ['all' => true],
    Phil\ContactFormBundle\PhilContactFormBundle::class => ['all' => true],
];
```

Without `PhilSpamProtectionBundle`, the container refuses to build. The route is imported, under the path the
application chooses:

```yaml
# config/routes/phil_contact_form.yaml
phil_contact_form:
    resource: '@PhilContactFormBundle/config/routes.php'
    prefix: /contact
```

The proof of work of the protection runs in the browser through its Stimulus controller: see
`phil/spam-protection-bundle`'s installation.
