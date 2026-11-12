<!-- phil-dev source-sha256=56ad8b0855734f8da99d0a91c2661e798dc54b195890a662038098e830c6d1bc -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/contact` | ^0.1@dev | Les fiches |
| `phil/phone-bundle`, `phil/url-bundle`, `phil/instant-messenger-bundle` | ^0.1@dev | Les champs du formulaire de fiche |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | Le formulaire de fiche |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `doctrine/dbal` | facultatif | Le type de colonne `phil_contact` |
| `symfony/http-foundation` | facultatif | `VCardResponse`, `JSContactResponse` |
| `twig/twig` | facultatif | Les fonctions et le thème de formulaire |

```sh
composer require phil/contact-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\PhoneBundle\PhilPhoneBundle::class => ['all' => true],
    Phil\UrlBundle\PhilUrlBundle::class => ['all' => true],
    Phil\InstantMessengerBundle\PhilInstantMessengerBundle::class => ['all' => true],
    Phil\ContactBundle\PhilContactBundle::class => ['all' => true],
];
```

Les numéros de téléphone saisis dans le formulaire sont lus dans `phil_phone.default_region`. Avec Symfony UX, le
contrôleur Stimulus se trouve dans `assets/`, déclaré dans son `package.json`.

## Configuration

```yaml
# config/packages/phil_contact.yaml
phil_contact:
    jscontact:
        max_bytes: 65536      # the longest card the JSContact reader reads
        max_depth: 16         # the deepest nesting of its JSON, 2 or more
        max_entries: 100      # the most entries of one map or list
        region: ~             # the country of phone numbers without their country code: "CA"
```

Chaque clé est facultative ; les valeurs ci-dessus sont les valeurs par défaut.
