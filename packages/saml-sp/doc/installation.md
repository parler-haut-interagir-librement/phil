# Installation

```sh
composer require phil/saml-sp
```

The library needs PHP 8.5 or later with the `dom`, `libxml`, `openssl`, `sodium` and `zlib` extensions, and:

- `phil/url`, which reads every address the library receives — the destination of a response, the services of
  metadata — and compares them in their normalised form;
- `phil/safe-http`, through which `SafeHttpMetadataFetcher` fetches the metadata of an identity provider;
- `psr/clock`, the clock every time check reads;
- `psr/cache` and `symfony/cache-contracts`, the cache pools of the stores and of the metadata;
- optionally `doctrine/dbal` 4.5 or later, for the database replay guard `DbalReplayGuard`;
- `symfony/service-contracts`, for `ResetInterface`.

No third-party SAML or XML security library is used: signatures are verified with `ext-dom`'s exclusive
canonicalisation and `ext-openssl`, assertions decrypted with `ext-openssl`.

In a Symfony application, phil/saml-sp-bundle builds these objects from the configuration, serves the routes and signs
the person in through Symfony Security.

## Without a framework

```php
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Http\SafeHttpMetadataFetcher;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Key\PrivateKey;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Store\CachePendingRequests;
use Phil\SamlSp\Store\CacheReplayGuard;
use Phil\Url\Url;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Clock\NativeClock;

$clock = new NativeClock();
$pool = new RedisAdapter(RedisAdapter::createConnection($_ENV['REDIS_URL']), 'saml');
$keys = new KeyPair(PrivateKey::fromPem(file_get_contents('/run/secrets/saml.key')), Certificate::fromPem(file_get_contents('/etc/saml/saml.crt')));

$sp = new ServiceProvider(
    'https://app.example.org/saml/metadata',
    Url::fromString('https://app.example.org/saml/acs'),
    Url::fromString('https://app.example.org/saml/slo'),
    signing: $keys,
    encryption: [$keys],
);

$metadata = new MetadataLoader(new SafeHttpMetadataFetcher(), $clock, $pool)
    ->fromUrl(Url::fromString('https://login.microsoftonline.com/<tenant>/federationmetadata/2007-06/federationmetadata.xml?appid=<app>'));
$idp = new IdentityProvider($metadata, AttributeMap::entraId());

$factory = new MessageFactory($clock);
$requests = new CachePendingRequests($pool);
$processor = new ResponseProcessor($clock, new CacheReplayGuard($pool));
```

The cache pool of the stores is shared by every worker and every server of the application, and is not one a deploy
clears: an assertion forgotten by the replay guard could be accepted a second time. With Doctrine DBAL installed,
`DbalReplayGuard` keeps the accepted assertions in a table instead, atomic on any number of servers: see
[The replay store](replay-store.md).
