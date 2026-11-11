<!-- phil-dev source-sha256=bf9ebbecad06749a3bd956080bd6d7d2e8c44fbdf9c814dbebef8767517e1d7b -->
# Points d'extension

La bibliothèque a quatre ports, les interfaces de `Phil\OpenBadges\Contract`. Chacun a une implémentation dans la
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

## `VerificationMethods`

Trouve la clé que nomme une preuve ou un jeton :

```php
interface VerificationMethods
{
    public function key(string $verificationMethod): VerificationKey;   // InvalidCredential when unknown
}
```

`DidKeyMethods` connaît les clés de l'application et chaque `did:key` Ed25519. Pour vérifier des titres d'émetteurs
qu'elle ne connaît pas d'avance, une application implémente ce port. Elle peut télécharger le document de profil de
l'émetteur, lire son `verificationMethod` — `Ed25519Key::fromMultibase()` décode une Multikey —, vérifier que le profil
l'utilise pour ses assertions et garder ce qu'elle a téléchargé. Télécharger relève de la décision de l'application,
avec ses propres limites d'hôtes, de tailles et de temps. Quoi que le port renvoie, `CredentialVerifier` n'accepte une
clé que pour son propre émetteur (voyez [Vérifier](verifying.md)).

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
