<!-- phil-dev source-sha256=8873796d1c211bcf64f71bcbd91ee6a4d8c957ec4579b8fc283bd4c2595e3d1b -->
# Installation

```sh
composer require phil/virtual-classroom
```

La bibliothèque a besoin de PHP 8.5 ou d'une version plus récente avec les extensions `dom`, `libxml`, `mbstring`,
`openssl` et `sodium`, et de :

- `phil/url`, qui lit chaque adresse que la bibliothèque écrit ou reçoit ;
- `psr/clock`, l'horloge qui date les événements et vérifie les jetons ;
- `symfony/http-client-contracts`, le client HTTP avec lequel l'adaptateur BigBlueButton appelle son serveur.
  Installez une implémentation, `symfony/http-client`, pour employer cet adaptateur ; l'adaptateur Jitsi n'appelle
  aucun serveur.

Dans une application Symfony, phil/virtual-classroom-bundle construit les adaptateurs à partir de la configuration,
conserve les salles et l'assiduité, et reçoit les notifications.

Sans le bundle, construisez un adaptateur avec le client HTTP et l'horloge de l'application :

```php
use Phil\Url\Url;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\Jitsi\Jitsi;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\HttpClient;

$clock = new NativeClock();
$bbb = new BigBlueButton(HttpClient::create(), Url::fromString('https://bbb.example.com/bigbluebutton/api/'), $_ENV['BBB_SECRET'], $clock);
$jitsi = Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'my-app', $_ENV['JITSI_SECRET'], $clock);
```
