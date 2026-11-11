<!-- phil-dev source-sha256=2482b9e7d508d1ebe97dcc7d266203ff0cc8de0885f7f437c188d79eadda4b1d -->
# Identifiants

`Handle::of($service, $input)` lit un identifiant et le garde sous forme canonique ; il lance `InvalidHandle` pour une
entrée que le service ne lit pas.

```php
$handle = Handle::of(KnownMessenger::WhatsApp, '+1 (514) 555-0123');
$handle->value;     // "+15145550123"
$handle->link();    // "https://wa.me/15145550123"
$handle->equals(Handle::of(KnownMessenger::WhatsApp, 'wa.me/15145550123'));   // true
```

## Un identifiant conservé

Un identifiant est conservé sous forme de son service et de sa valeur canonique, `$handle->value`.
`Handle::fromCanonical($service, $value)` le reprend tel quel, sans le relire : il a été lu au moment où il a été
conservé. Le relire avec `of()` refuserait un numéro de téléphone qu'une importation ultérieure des plans de
numérotation de `phil/phone` ne connaît plus, et l'entité qui le contient ne pourrait pas être chargée. Un texte tapé
par une personne passe par `of()`.

```php
Handle::fromCanonical(KnownMessenger::WhatsApp, $row['handle']);
```

## Détecter le service

```php
Messengers::detect('https://t.me/adalove', EnumSet::all(KnownMessenger::class));   // the Telegram handle, or null
Messengers::handle('@ada:matrix.org', $accepted);                                  // or InvalidHandle
```

Le premier service qui lit l'entrée l'emporte, ensemble par ensemble et dans l'ordre de déclaration de chaque enum ;
l'entrée est lue une seule fois, et l'identifiant est la forme canonique que ce service a donnée. **Les identifiants se
chevauchent** : un nom d'utilisateur peut être lu aussi bien par Telegram que par Line et Discord, et un numéro de
téléphone par WhatsApp, Signal, Viber et iMessage. La détection est juste pour les liens, qui nomment leur service, et
pour les formes distinctives (un identifiant Matrix, une adresse XMPP) ; ailleurs, demandez le service à la personne,
comme le fait le champ de `phil/instant-messenger-bundle`.
