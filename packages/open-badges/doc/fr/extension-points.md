<!-- phil-dev source-sha256=9c38c3cf73f3b75a8bf2556672ad1190e48fdca1d77ef5ca070d39f8afae778e -->
# Points d'extension

La bibliothèque a cinq ports, les interfaces de `Phil\OpenBadges\Contract`. Chacun a une implémentation dans la
bibliothèque ; une application en implémente un pour ce que la bibliothèque lui laisse.

## `Signer`

La clé privée de l'émetteur :

```php
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;

interface Signer
{
    public function algorithm(): string;               // the JSON Web Algorithm: EdDSA, RS256
    public function signatureAlgorithm(): SignatureAlgorithm;   // the web-token/jwt-library algorithm of that name
    public function jwk(): JWK;                        // the key that algorithm signs with
    public function publicJwk(): array;                // the public key as a JSON Web Key
    public function verificationKey(): VerificationKey;
}
```

Les jetons sont construits, signés et sérialisés par `web-token/jwt-library` : `JwtSigner` lui remet
`signatureAlgorithm()` et `jwk()`. `Ed25519Signer` et `RsaSigner` gardent la clé en mémoire, `jwk()` étant la JSON Web
Key privée. Un signataire qui repose sur un service de gestion de clés — la clé privée ne le quittant jamais — retourne
son propre `SignatureAlgorithm`, qui signe par le service, et la JSON Web Key publique comme `jwk()` ; il se donne à
`JwtSigner` et à `CredentialIssuer`. Les preuves Data Integrity prennent un `Ed25519Signer`.

## `VerificationKey`

Une clé publique : `algorithm()`, l'unique JSON Web Algorithm qu'elle vérifie, et `jwk()`, la JSON Web Key publique de
`web-token/jwt-library`. `Ed25519Key` et `RsaKey` l'implémentent. Le vérificateur prend l'algorithme de la clé, jamais
du jeton, et ne connaît que `EdDSA` et `RS256` : une clé qui nomme un autre algorithme ne vérifie rien.
`Ed25519Key::fromJwk()`, `Ed25519Key::fromMultibase()`, `RsaKey::fromJwk()` et `RsaKey::fromPem()` lisent les clés
qu'un émetteur publie.

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
