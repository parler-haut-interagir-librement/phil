# Installation

```sh
composer require phil/virtual-classroom
```

The library needs PHP 8.5 or later with the `dom`, `libxml`, `mbstring`, `openssl` and `sodium` extensions, and:

- `phil/url`, which reads every address the library writes or receives;
- `psr/clock`, the clock that dates the events and checks the tokens;
- `symfony/http-client-contracts`, the HTTP client the BigBlueButton adapter calls its server with. Install an
  implementation, `symfony/http-client`, to use that adapter; the Jitsi adapter calls no server.

In a Symfony application, phil/virtual-classroom-bundle builds the adapters from the configuration, keeps the rooms
and the attendance, and receives the notifications.

Without the bundle, build an adapter with the HTTP client and the clock of the application:

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
