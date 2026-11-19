<!-- phil-dev source-sha256=0b128dca158aef66ab740a8fdd86db12523b17db35ff00643a6088aed5403ad8 -->
# Jetons OAuth

## Un jeton

`OAuthToken` conserve le jeton d'accès, le jeton d'actualisation quand le service en a émis un, l'expiration et les
portées accordées. Construisez-le à partir de la réponse du service :

```php
$token = OAuthToken::of($accessToken, $refreshToken, new DateTimeImmutable('+3600 seconds'), ['repo', 'read:user']);
```

Un jeton sans expiration n'expire jamais, du point de vue du bundle.

## Lire un jeton

`UserSecrets::accessToken()` renvoie le jeton d'un secret. Quand il a expiré, ou qu'il expire dans moins de
`refresh_leeway` secondes, le bundle demande d'abord un nouveau jeton aux fournisseurs d'actualisation de
l'application. Le premier qui prend en charge le service du secret le renouvelle. Le nouveau jeton est scellé et
enregistré avec la lecture, et un événement `refreshed` va aux auditeurs avant l'événement `revealed`. Quand le service
ne renvoie aucun jeton d'actualisation, le précédent est conservé.

## Actualisations concurrentes

Deux processus peuvent lire en même temps le même jeton expiré — un *worker* et une requête, deux *workers*. Beaucoup de
services font tourner le jeton d'actualisation à chaque utilisation et, quand un ancien jeton d'actualisation revient,
révoquent chaque jeton de l'autorisation : deux actualisations d'un même jeton pourraient mettre fin à la connexion du
propriétaire. Le bundle s'en garde de deux façons.

- **Le verrou d'actualisation.** Avec `symfony/lock` installé et `framework.lock` configuré, une actualisation
  s'exécute sous un verrou de ce secret, pris dans le `lock.factory` de FrameworkBundle ou dans la fabrique que nomme
  `lock_factory`. Le processus qui le prend relit la ligne : quand un autre processus a actualisé le jeton pendant qu'il
  attendait, il utilise ce jeton et n'appelle pas le service. Le verrou dure au plus `refresh_lock_ttl` secondes.
  Utilisez un magasin que tous les processus partagent — Redis, une base de données, `flock` sur un seul hôte —, pas
  `semaphore` entre plusieurs hôtes.
- **Le compare-and-swap.** Le jeton actualisé n'est enregistré que si la ligne détient encore la valeur scellée avec
  laquelle il a été lu — un seul `UPDATE` conditionnel. Quand un autre processus a changé la ligne entre-temps, le
  bundle la relit et utilise le jeton de ce processus s'il est valide ; sinon, il enregistre le sien par-dessus la
  nouvelle ligne, au plus `UserSecrets::REFRESH_ATTEMPTS` fois, puis échoue avec `RefreshConflict`. Sans verrou, cela
  garde un seul jeton dans le stockage, mais les deux processus ont appelé le service.

Un jeton révoqué ou supprimé pendant son actualisation le reste : l'actualisation échoue avec `RevokedSecret` ou
`SecretNotFound`.

## Erreurs

Un jeton expiré qui ne peut pas être actualisé — il n'a pas de jeton d'actualisation, ou aucun fournisseur
d'actualisation ne dessert son service — échoue avec `ExpiredToken` : le propriétaire doit connecter le service de
nouveau. Lire une clé d'API comme un jeton, ou un jeton avec `apiKey()`, échoue avec `WrongCredentialKind`.

## Écrire un fournisseur d'actualisation

Un fournisseur d'actualisation implémente `TokenRefresher` ; la configuration automatique lui donne son étiquette. En
général, il envoie une autorisation `refresh_token` au point de terminaison des jetons du service :

```php
use Phil\UserSecretsBundle\Application\Contract\TokenRefresher;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GitHubRefresher implements TokenRefresher
{
    public function __construct(private HttpClientInterface $client, private string $clientId, private string $clientSecret)
    {
    }

    public function supports(ServiceName $service): bool
    {
        return 'github' === $service->value;
    }

    public function refresh(ServiceName $service, OAuthToken $token): OAuthToken
    {
        $data = $this->client->request('POST', 'https://github.com/login/oauth/access_token', [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refreshToken?->reveal(),
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ])->toArray();

        return OAuthToken::of($data['access_token'], $data['refresh_token'] ?? null, new DateTimeImmutable('+'.$data['expires_in'].' seconds'), explode(',', $data['scope'] ?? ''));
    }
}
```

Quand un fournisseur d'actualisation lève une exception, celle-ci traverse `accessToken()` ; le jeton stocké reste tel
quel.

## Écrire un révocateur

Un révocateur implémente `TokenRevoker` ; la configuration automatique lui donne son étiquette. Le bundle l'appelle
quand un jeton est révoqué, supprimé ou effacé avec le compte, une fois le jeton disparu localement. En général, il
envoie le jeton d'actualisation, quand il y en a un, au point de terminaison de révocation du service, comme le décrit
la RFC 7009 — révoquer le jeton d'actualisation met aussi fin aux jetons d'accès qu'il a émis :

```php
use Phil\UserSecretsBundle\Application\Contract\TokenRevoker;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExampleRevoker implements TokenRevoker
{
    public function __construct(private HttpClientInterface $client, private string $clientId, private string $clientSecret)
    {
    }

    public function supports(ServiceName $service): bool
    {
        return 'example' === $service->value;
    }

    public function revoke(ServiceName $service, OAuthToken $token): void
    {
        $refresh = $token->refreshToken;

        $this->client->request('POST', 'https://auth.example.com/oauth/revoke', [
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'body' => null === $refresh
                ? ['token' => $token->accessToken->reveal(), 'token_type_hint' => 'access_token']
                : ['token' => $refresh->reveal(), 'token_type_hint' => 'refresh_token'],
        ])->getContent();   // throws on a 4xx or 5xx answer
    }
}
```

La RFC 7009 fait répondre 200 au service pour un jeton qu'il ne connaît plus : révoquer deux fois est donc sans
conséquence. Un révocateur qui lève une exception est journalisé et audité, jamais levé jusqu'à l'appelant : gardez le
jeton hors du message de l'exception, car le bundle le journalise.
