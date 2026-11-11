<!-- phil-dev source-sha256=bb7143d1806cd16f248abd89230ddddb6acb0b2058be257e553acef72ec94039 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-filter` | toute version | La vérification des adresses de la configuration |
| `phil/spam-protection-bundle` | ^0.1@dev | La protection du formulaire |
| `symfony/form`, `symfony/options-resolver`, `symfony/validator` | ^8.2 | Le formulaire et ses vérifications |
| `symfony/mailer`, `symfony/mime`, `symfony/twig-bridge`, `twig/twig` | ^8.2, ^3.21 | Le courriel et la page |
| `symfony/http-foundation`, `symfony/dependency-injection`, `symfony/config` | ^8.2 | Le contrôleur et le bundle |
| `psr/log` | ^3.0 | Un message que le transport refuse est journalisé |

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

Sans `PhilSpamProtectionBundle`, le conteneur refuse de se construire. La route est importée, sous le chemin que
l'application choisit :

```yaml
# config/routes/phil_contact_form.yaml
phil_contact_form:
    resource: '@PhilContactFormBundle/config/routes.php'
    prefix: /contact
```

La preuve de travail de la protection s'exécute dans le navigateur par son contrôleur Stimulus : voir l'installation de
`phil/spam-protection-bundle`.
