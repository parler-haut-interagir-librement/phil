# Extension points

The library has five contracts, all interfaces of `Phil\IpAnonymizer\Contract`. The application implements any of
them; every built-in class takes them as constructor arguments.

## `IpAnonymizer`

A strategy of the application's own: it receives a parsed address and returns the text kept in its place.

```php
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\IpAddress;

final readonly class Redacted implements IpAnonymizer
{
    public function anonymize(IpAddress $address): string
    {
        return 'redacted';
    }
}
```

It composes with the built-in ones: as the last step of a `Pipeline`, a member of `PerFamily`, the fallback of
`LabelGeneralisation`.

## `IpTransformer`

A step that turns an address into another address before the strategy of a `Pipeline`. `Truncation` is one.

```php
interface IpTransformer
{
    public function transform(IpAddress $address): IpAddress;
}
```

## `SaltProvider`

The salt of `KeyedHash`, asked on every digest:

```php
interface SaltProvider
{
    public function current(): Salt;
}
```

`ForgottenSalt`, `FixedSalt` and `RotatingSalt` are the built-in providers. A provider of the application's own may read
a salt from its secrets manager.

## `ExpiringSaltStore`

Where the servers of an application share the random salt of a period, for `ForgottenSalt`:

```php
interface ExpiringSaltStore
{
    public function claim(string $saltId, #[SensitiveParameter] string $candidate, int $ttlSeconds): string;
}
```

`claim()` returns the bytes held under the identifier; when there are none, it stores the candidate for at most
`$ttlSeconds` and returns it. Keep the step atomic, or two servers that find the entry missing draw two salts and
digest one address two ways until one write wins.

With Redis, one command holds the whole contract:

```php
use Phil\IpAnonymizer\Contract\ExpiringSaltStore;

final readonly class RedisSaltStore implements ExpiringSaltStore
{
    public function __construct(
        private \Redis $redis,
    ) {
    }

    public function claim(string $saltId, #[\SensitiveParameter] string $candidate, int $ttlSeconds): string
    {
        $key = 'ip-salt.'.$saltId;
        $this->redis->set($key, $candidate, ['nx', 'ex' => $ttlSeconds]);

        return $this->redis->get($key);
    }
}
```

A `Symfony\Contracts\Cache\CacheInterface` works too, by `get()` with a callback that sets the expiry and returns the
candidate; put a lock around it on a pool whose `get()` does not serialize the callback across servers.

Whichever backs it, the store must not keep an entry past its expiry: no append-only file, no snapshot, no backup, as
any of them would keep the salts it was meant to forget. A store that cannot answer throws: the digest then fails
rather than being taken with a salt the other servers do not share.

## `NetworkLabeller`

The labels of `LabelGeneralisation`. `NetworkLabels` is the built-in table; an application whose regions live
elsewhere — a database table of ranges, a file it compiles — writes its own:

```php
interface NetworkLabeller
{
    public function labelOf(IpAddress $address): ?string;
}
```

A labeller answers `null` when no network it knows holds the address, so that the fallback strategy decides.

## Framework adapters

The library depends on no framework; an adapter is a few lines in the application.

A log processor that replaces the client address of each record:

```php
use Monolog\LogRecord;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\IpAddress;

final readonly class AnonymizedClientIp
{
    public function __construct(
        private IpAnonymizer $anonymizer,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $address = IpAddress::tryParse((string) ($record->extra['client_ip'] ?? ''));

        return $record->with(extra: ['client_ip' => null === $address ? null : $this->anonymizer->anonymize($address)] + $record->extra);
    }
}
```

A Twig filter, for a back office that shows addresses:

```php
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\IpAddress;
use Twig\Attribute\AsTwigFilter;

final readonly class IpFilter
{
    public function __construct(
        private IpAnonymizer $anonymizer,
    ) {
    }

    #[AsTwigFilter('anonymize_ip')]
    public function anonymize(string $text): string
    {
        $address = IpAddress::tryParse($text);

        return null === $address ? '' : $this->anonymizer->anonymize($address);
    }
}
```
