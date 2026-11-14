<!-- phil-dev source-sha256=ecf90a483427dd77dacb435d5e243e41060d32db0be910671bf5cc4ac7f7c61f -->
# Identifiants

Une base dédiée est atteinte, par défaut, sur le serveur de la connexion partagée, avec son compte, sous son propre
nom. Une base sur un autre serveur, ou derrière un compte à elle, reçoit ses identifiants de l'application par
`TenantCredentials` :

```php
use Phil\TenancyDatabaseBundle\Application\Contract\TenantCredentials;
use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;
use Phil\TenancyDatabaseBundle\Infrastructure\Credentials\CredentialSeal;

final readonly class StoredCredentials implements TenantCredentials
{
    public function __construct(
        private Connection $platform,      // a connection that does not follow the tenant
        private CredentialSeal $seal,
    ) {
    }

    public function credentialsFor(string $database): ?DatabaseCredentials
    {
        $sealed = $this->platform->fetchOne('SELECT credentials FROM tenant_database WHERE name = ?', [$database]);

        return \is_string($sealed) ? $this->seal->open($database, $sealed) : null;
    }
}
```

```yaml
phil_tenancy_database:
    credentials: App\Tenancy\StoredCredentials
```

`credentialsFor()` est appelé chaque fois que la connexion s'ouvre sur cette base ; retournez `null` pour garder le
compte de la connexion partagée.

## `DatabaseCredentials`

Un utilisateur, un mot de passe, et facultativement un hôte et un port. Le constructeur refuse un utilisateur vide, un
hôte vide, un caractère de contrôle dans une valeur et un port hors de 1 à 65535. Le mot de passe est un paramètre
sensible, absent des traces de pile ; un export le montre comme `***` ; sérialiser l'objet lance une exception, pour
qu'il n'atteigne jamais une entrée de cache ni un message Messenger.

## Le format scellé

Les identifiants stockés dans une base sont scellés par `CredentialSeal`, sous une clé de 32 octets gardée hors de
cette base :

```text
ptdb.v1.xchacha20poly1305.<base64url(nonce ‖ ciphertext)>
```

- **Nommé et versionné** : le préfixe donne le format, sa version et son algorithme, XChaCha20-Poly1305 de libsodium.
  Une valeur d'un autre format, d'une autre version ou d'un autre algorithme est refusée, jamais lue comme celle-ci.
- **Lié à sa base** : le nom de la base est authentifié avec la valeur, si bien que des identifiants scellés pour une
  base ne s'ouvrent pas pour une autre, même sous la même clé.
- **Borné** : une valeur scellée de plus de 4 Kio est refusée avant d'être décodée.

```php
$seal = new CredentialSeal(sodium_base642bin($_ENV['TENANT_CREDENTIALS_KEY'], SODIUM_BASE64_VARIANT_ORIGINAL));
$sealed = $seal->seal('tenant_retail', new DatabaseCredentials('retail', $password, 'db2.internal'));
```

## Ce qui n'est jamais écrit

- Les lignes de journal du bundle nomment la référence d'un locataire et un nom de base, jamais un hôte, un utilisateur
  ni un mot de passe.
- Un message Messenger ne porte que la référence du locataire.
- Ce qu'un processus de migration affiche est montré sur la console de l'opérateur, jamais journalisé.
- Un visiteur qui reçoit un `503` lit que le site est en maintenance, rien de plus.
