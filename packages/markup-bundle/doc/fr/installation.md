<!-- phil-dev source-sha256=f62cd89522a32b1c2edc60bf62e401242faea4482a7489d33c952ad50d206fcb -->
# Installation

## Prérequis

| Paquet | Version | Utilisé pour |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/markup` | ^0.1.2 | Les formats, les niveaux, les analyseurs, le compilateur et le purificateur |
| `phil/ujo` | ^0.1 | Le moteur de rendu UJO et le JSON qu'écrit le convertisseur |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |
| `symfony/translation-contracts` | ^3.6 | La locale courante de la ponctuation soignée, lue dans le `LocaleSwitcher` de Symfony |
| `phil/typography` | ^0.1, facultatif | La ponctuation soignée dans chaque format, seulement lorsqu'il est installé |
| `symfony/form` | ^8.2, facultatif | Le type de formulaire, enregistré seulement lorsque Symfony Form est installé |
| `twig/twig` | ^3.21, facultatif | Les filtres, enregistrés seulement lorsque Twig est installé |

## Enregistrer

```php
// config/bundles.php
return [
    // ...
    Phil\MarkupBundle\PhilMarkupBundle::class => ['all' => true],
];
```

Sans configuration, tous les formats sont activés, le Markdown est le format par défaut, les filtres purifient à `all`,
et chaque format reçoit la ponctuation soignée lorsque `phil/typography` est installé : voir
[Configuration](configuration.md).
