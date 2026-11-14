<!-- phil-dev source-sha256=1d4639fb53ea7ba98996e1ac67d53e17fb0cf01f2bef8b0e4088cc68293b0410 -->
# Points d'extension

La bibliothèque a cinq ports, les interfaces de `Phil\OpenBadges\Contract`. Chacun a une implémentation dans la
bibliothèque ; une application en implémente un pour ce que la bibliothèque lui laisse.

## `Signer`

La clé privée de l'émetteur :

```php
interface Signer
{
    public function algorithm(): string;               // the JSON Web Algorithm: EdDSA, RS256
    public function sign(string $data): string;
    public function publicJwk(): array;                // the public key as a JSON Web Key
    public function verificationKey(): VerificationKey;
}
```

`Ed25519Signer` et `RsaSigner` gardent la clé en mémoire. Un signataire qui repose sur un service de gestion de clés —
la clé privée ne le quittant jamais — implémente l'interface et se donne à `JwtSigner` et à `CredentialIssuer`. Les
preuves Data Integrity prennent un `Ed25519Signer`.

## `VerificationKey`

Une clé publique : `algorithm()` et `verify(string $data, string $signature): bool`. `Ed25519Key` et `RsaKey`
l'implémentent. `verify()` répond false pour une signature de mauvaise longueur plutôt que de lancer une exception.
`Ed25519Key::fromJwk()`, `Ed25519Key::fromMultibase()` et `RsaKey::fromJwk()` lisent les clés qu'un émetteur publie.

## `VerificationMethods`

Trouve la clé que nomme une preuve ou un jeton :

```php
interface VerificationMethods
{
    public function key(string $verificationMethod): VerificationKey;   // InvalidCredential when unknown
}
```

`DidKeyMethods` connaît les clés de l'application et chaque `did:key` Ed25519 ; `DidWebMethods` lit les clés des
émetteurs `did:web` dans leurs documents DID, par un `DocumentFetcher`. Pour vérifier des titres d'émetteurs d'une
autre sorte, une application implémente ce port. Elle peut télécharger le document de profil de l'émetteur, lire son
`verificationMethod` — `Ed25519Key::fromMultibase()` décode une Multikey, `RsaKey::fromJwk()` une JSON Web Key —,
vérifier que le profil l'utilise pour ses assertions et garder ce qu'elle a téléchargé. Télécharger relève de la
décision de l'application, avec ses propres limites d'hôtes, de tailles et de temps. Quoi que le port renvoie,
`CredentialVerifier` n'accepte une clé que pour son propre émetteur (voyez [Vérifier](verifying.md)).

## `DocumentFetcher`

Lit le document DID d'un `did:web` à son adresse HTTPS, pour `DidWebMethods` :

```php
interface DocumentFetcher
{
    public function fetch(Url $url): string;           // InvalidCredential when it cannot be read
}
```

`SafeHttpFetcher` le lit par `phil/safe-http`. Un décorateur garde les documents un moment :

```php
use Phil\OpenBadges\Contract\DocumentFetcher;
use Phil\Url\Url;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class CachedDocuments implements DocumentFetcher
{
    public function __construct(private DocumentFetcher $fetcher, private CacheInterface $cache) {}

    public function fetch(Url $url): string
    {
        return $this->cache->get('did.'.hash('xxh128', $url->toString()), function ($item) use ($url): string {
            $item->expiresAfter(300);

            return $this->fetcher->fetch($url);
        });
    }
}
```

Un document qui n'a pu être lu lance `InvalidCredential`, que le cache ne garde pas. Un lecteur des tests sert des
documents depuis la mémoire.

## `ContextLoader`

Donne le document de contexte JSON-LD d'une URL, avec son `@context` :

```php
interface ContextLoader
{
    public function load(string $url): array;          // InvalidCredential when unknown
}
```

`BundledContexts` sert les contextes que la bibliothèque inclut et ceux donnés à son constructeur :

```php
new EddsaRdfc2022(new BundledContexts(['https://example.org/contexts/v1' => $decodedContext]));
```

Un chargeur qui télécharge les contextes laisse un document choisir ce que signifient ses propres termes, et rend
chaque vérification dépendante du réseau : incluez plutôt les contextes qu'une application accepte.
