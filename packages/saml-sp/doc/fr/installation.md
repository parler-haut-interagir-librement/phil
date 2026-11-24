<!-- phil-dev source-sha256=fa0642eb47df0e8dbaf447136864861d74598bf898458d6f791cb9d1d37f27bd -->
# Installation

```sh
composer require phil/saml-sp
```

La bibliothèque demande PHP 8.5 ou une version plus récente, avec les extensions `dom`, `libxml`, `openssl`, `sodium` et
`zlib`, ainsi que :

- `phil/url`, qui lit chaque adresse que la bibliothèque reçoit — la destination d'une réponse, les services des
  métadonnées — et les compare sous leur forme normalisée ;
- `phil/safe-http`, par lequel `SafeHttpMetadataFetcher` récupère les métadonnées d'un fournisseur d'identité ;
- `psr/clock`, l'horloge que lit chaque contrôle de temps ;
- `psr/cache` et `symfony/cache-contracts`, les réserves de cache des magasins et des métadonnées ;
- facultativement `doctrine/dbal` 4.5 ou plus, pour la garde de rejeu en base de données `DbalReplayGuard` ;
- `symfony/service-contracts`, pour `ResetInterface`.

Aucune bibliothèque tierce de SAML ou de sécurité XML n'est utilisée : les signatures sont vérifiées avec la
canonicalisation exclusive d'`ext-dom` et avec `ext-openssl`, et les assertions sont déchiffrées avec `ext-openssl`.

Dans une application Symfony, phil/saml-sp-bundle construit ces objets à partir de la configuration, sert les routes et
connecte la personne par Symfony Security.

## Sans cadre

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

La réserve de cache des magasins est partagée par chaque *worker* et chaque serveur de l'application, et ce ne doit pas
être une réserve qu'un déploiement vide : une assertion oubliée par la garde de rejeu pourrait être acceptée une
seconde fois. Avec Doctrine DBAL installé, `DbalReplayGuard` garde plutôt les assertions acceptées dans une table,
atomique sur un nombre quelconque de serveurs : voir [Le magasin de rejeu](replay-store.md).
