<!-- phil-dev source-sha256=1f2a145be5fe7ff16504bcde64c2874b9fc91b012cafa568dc4157ab3f1fd502 -->
# Points d'extension

La bibliothèque a cinq contrats, tous des interfaces de `Phil\IpAnonymizer\Contract`. L'application implémente
n'importe lequel d'entre eux ; chaque classe intégrée les prend en arguments de constructeur.

## `IpAnonymizer`

Une stratégie propre à l'application : elle reçoit une adresse analysée et retourne le texte conservé à sa place.

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

Elle se compose avec les stratégies intégrées : comme dernière étape d'un `Pipeline`, comme membre de `PerFamily`, comme
stratégie de repli de `LabelGeneralisation`.

## `IpTransformer`

Une étape qui transforme une adresse en une autre adresse avant la stratégie d'un `Pipeline`. `Truncation` en est une.

```php
interface IpTransformer
{
    public function transform(IpAddress $address): IpAddress;
}
```

## `SaltProvider`

Le sel de `KeyedHash`, demandé à chaque empreinte :

```php
interface SaltProvider
{
    public function current(): Salt;
}
```

`ForgottenSalt`, `FixedSalt` et `RotatingSalt` sont les fournisseurs intégrés. Un fournisseur propre à l'application
peut lire un sel dans son gestionnaire de secrets.

## `ExpiringSaltStore`

L'endroit où les serveurs d'une application partagent le sel aléatoire d'une période, pour `ForgottenSalt` :

```php
interface ExpiringSaltStore
{
    public function claim(string $saltId, #[SensitiveParameter] string $candidate, int $ttlSeconds): string;
}
```

`claim()` retourne les octets conservés sous l'identifiant ; s'il n'y en a pas, il conserve le candidat pendant au plus
`$ttlSeconds` et le retourne. Gardez cette étape atomique, sinon deux serveurs qui trouvent l'entrée absente tirent deux
sels et réduisent une même adresse de deux façons jusqu'à ce qu'une écriture l'emporte.

Avec Redis, une seule commande tient tout le contrat :

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

Un `Symfony\Contracts\Cache\CacheInterface` convient aussi, par `get()` avec une fonction de rappel qui fixe
l'expiration et retourne le candidat ; entourez-le d'un verrou sur un pool dont le `get()` ne sérialise pas la fonction
de rappel d'un serveur à l'autre.

Quel que soit ce qui le porte, le magasin ne doit garder aucune entrée au-delà de son expiration : pas de fichier en
ajout seul, pas d'instantané, pas de sauvegarde, car chacun garderait les sels qu'il devait oublier. Un magasin qui ne
peut pas répondre lève une exception : l'empreinte échoue alors plutôt que d'être calculée avec un sel que les autres
serveurs ne partagent pas.

## `NetworkLabeller`

Les libellés de `LabelGeneralisation`. `NetworkLabels` est la table intégrée ; une application dont les régions vivent
ailleurs (une table de plages en base de données, un fichier qu'elle compile) écrit la sienne :

```php
interface NetworkLabeller
{
    public function labelOf(IpAddress $address): ?string;
}
```

Un étiqueteur répond `null` quand aucun réseau qu'il connaît ne contient l'adresse, pour que la stratégie de repli
décide.

## Adaptateurs de cadriciel

La bibliothèque ne dépend d'aucun cadriciel ; un adaptateur tient en quelques lignes dans l'application.

Un processeur de journal qui remplace l'adresse du client de chaque enregistrement :

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

Un filtre Twig, pour une interface d'administration qui affiche des adresses :

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
