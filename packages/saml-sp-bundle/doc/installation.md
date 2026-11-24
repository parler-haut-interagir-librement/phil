# Installation

```sh
composer require phil/saml-sp-bundle
```

The Flex recipe registers `PhilSamlSpBundle`, writes `config/packages/phil_saml_sp.yaml`, imports the routes under
`/saml` and adds `SAML_SP_CERTIFICATE` and `SAML_SP_PRIVATE_KEY` to `.env`: the paths of the key pair of the service
provider. Without Flex, register the bundle in `config/bundles.php` and copy the two files from `recipe/0.1/config`.

## The key pair

The service provider signs its requests and its logout messages, and decrypts the assertions sent to it, with an RSA
key pair. Make one for each environment, outside the repository:

```sh
mkdir -p config/saml
openssl req -x509 -newkey rsa:3072 -sha256 -nodes -days 1095 \
    -subj "/CN=app.example.org SAML" -keyout config/saml/sp.key -out config/saml/sp.crt
chmod 600 config/saml/sp.key
echo '/config/saml/' >> .gitignore
```

The certificate is self-signed on purpose: SAML trusts the certificate published in the metadata, not a chain. In
production, keep the key in a secret store and point `SAML_SP_PRIVATE_KEY` at the file it mounts. Before the
certificate expires, `phil:saml:check` says so; publish the next one in `encryption` as well (see
[Configuration](configuration.md#key-rollover)).

## The firewall

The authenticator runs in the firewall that covers `/saml`:

```yaml
security:
    providers:
        saml: { id: phil_saml_sp.user_provider }
    firewalls:
        main:
            lazy: true
            provider: saml
            custom_authenticators: [Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator]
            logout: { path: /logout }
```

`phil_saml_sp.user_provider` keeps the `SamlUser` of the session. An application with accounts of its own implements
`SamlAccounts` and names its own user provider instead (see [Accounts and roles](accounts.md)).

## The routes

| Route | Path | Method | Role |
|---|---|---|---|
| `phil_saml_sp_metadata` | `/saml/{idp}/metadata` | GET | The metadata of the service provider for this identity provider. |
| `phil_saml_sp_login` | `/saml/{idp}/login?target=/path` | GET | Sends the person to the identity provider; `target` is a local path. |
| `phil_saml_sp_acs` | `/saml/{idp}/acs` | POST | The assertion consumer service, run by the authenticator. |
| `phil_saml_sp_slo` | `/saml/{idp}/slo` | GET, POST | The logout service. |

`{idp}` is the key of an identity provider in the configuration. The prefix is the recipe's: change it in
`config/routes/phil_saml_sp.yaml` before giving the addresses to the identity providers, since they are part of the
metadata.

## The table of the accepted assertions

With DoctrineBundle and its connection `default` — or the one `replay_guard.connection` names —, the bundle keeps the
accepted assertions in the table `phil_saml_used_assertion` (`replay_guard.table`). Create it with a migration of the
application; `DbalReplayGuard::configureSchema()` adds it to a schema:

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Phil\SamlSp\Store\DbalReplayGuard;
use Symfony\Component\Clock\NativeClock;

final class Version20261120000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $target = new DbalReplayGuard($this->connection, new NativeClock())->configureSchema($schema);

        foreach ($this->platform->getAlterSchemaSQL($this->connection->createSchemaManager()->createComparator()->compareSchemas($schema, $target)) as $sql) {
            $this->addSql($sql);
        }
    }
}
```

The table and its columns are described in the replay store guide of phil/saml-sp. Expired rows stay until they are
purged: schedule `bin/console phil:saml:purge-assertions`, once an hour for instance. Without a connection, the
accepted assertions go to `cache_pool`, which must then be shared by every server in production (see
[Security](security.md#replay)).

## Stimulus

On the HTTP-POST binding, the login answers a page whose form carries the request; the Stimulus controller
`phil--saml-sp-bundle--auto-submit` posts it at once. Without StimulusBundle, the page shows a "Continue" button. With
AssetMapper, `composer require symfony/stimulus-bundle` registers the controller from `assets/package.json`.
