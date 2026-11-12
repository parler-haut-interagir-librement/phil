<!-- phil-dev source-sha256=2fa45c711301ef8597d03243518198a13fd433fd9d8d7a0ca4ae15ac096b033f -->
<!-- phil-dev source-sha256=0 -->
# Messages

Un constat porte un `Message` : une clé, par exemple `link-text.generic`, et les valeurs de ses paramètres
substituables, par exemple `['href' => '/booking', 'text' => 'here']`. `symfony/translation` le rédige dans une langue,
dans le domaine `phil_accessibility_check`.

```php
use Phil\AccessibilityCheck\Translation\Messages;

$report->message($finding);                       // in the report's locale, the policy's `locale`
$report->inLocale('de')->message($finding);       // in German
Messages::translate(Messages::translator(), $finding->message, 'pt-BR');
```

## Langues

`Translation\Messages::translator()` donne un `Translator` qui parle anglais (`en`), français (`fr`), espagnol (`es`),
allemand (`de`), italien (`it`) et portugais (`pt`), l'anglais en repli : `Translation\English`, `French`, `Spanish`,
`German`, `Italian` et `Portuguese` contiennent leurs messages, que `Messages::resources()` donne par locale. Une locale
se lit comme une étiquette de langue, avec `-` ou `_`, sans égard à la casse : `fr-ca` se lit `fr_CA`, qui prend les
messages de `fr_CA`, puis de `fr`, puis ceux de l'anglais. Ce qui n'est pas une étiquette de langue, comme la locale d'un
rapport relu d'un fichier, se lit comme de l'anglais. Une clé qu'aucune locale ne connaît est écrite telle quelle.

## Clés

Les clés des règles sont énumérées avec chaque règle dans [Règles](rules.md). `report.blocked`, avec `count` et
`message`, est le message avec lequel bloque la garde de publication. Un paramètre substituable s'écrit `{name}` dans un
message.

## Ajouter ou remplacer des messages

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Translation\Messages;

$translator = Messages::translator();
$translator->addResource('array', ['image-alt.missing' => 'De afbeelding {source} heeft geen tekstalternatief.'], 'nl', Messages::DOMAIN);
$translator->addResource('array', ['link-text.generic' => 'Le lien vers {href} dit seulement « {text} ».'], 'fr_CA', Messages::DOMAIN);
$translator->addResource('array', ['my-rule.found' => 'Found {what}.'], 'en', Messages::DOMAIN);

$checker = new AccessibilityChecker(translator: $translator);
```

Une ressource ajoutée plus tard remplace les messages des mêmes clés dans sa locale. Une application donne plutôt au
vérificateur son propre traducteur, le service `translator` de Symfony avec `Messages::resources()` chargé dans le
domaine `phil_accessibility_check`, ou traduit `messageKey` avec `parameters`, que porte le JSON du rapport.
