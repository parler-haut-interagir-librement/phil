# Installation

```sh
composer require phil/organizations-bundle
```

Then register the bundle in `config/bundles.php`:

```php
Phil\OrganizationsBundle\PhilOrganizationsBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later with `ext-filter` and `ext-mbstring`, Symfony 8.2 or later and Doctrine DBAL 4.5
or later. `symfony/console` brings the commands, `symfony/security-core` the voter, `doctrine/orm` the schema
listener; `phil/privacy-bundle` and `phil/access-bundle` bring their integrations when they are installed.

## The key of the invitations

An invitation's token is kept only as its HMAC-SHA-256 under a key of at least 32 bytes. Read it from the secrets vault:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;' | bin/console secrets:set ORGANIZATIONS_INVITATION_KEY -
```

```yaml
# config/packages/phil_organizations.yaml
phil_organizations:
    invitations:
        hash_key: '%env(ORGANIZATIONS_INVITATION_KEY)%'
```

The configuration is refused without it. To rotate the key, see [Invitations](invitations.md#rotating-the-key).

## The tables

The default store keeps the organizations in six tables of the `default` DBAL connection, named in
[the reference](reference.md#the-tables). With the ORM, the bundle adds them to the schema the ORM generates, so that
`doctrine:migrations:diff` writes the migration that creates them:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, a migration builds them from `OrganizationTables`, for the platform of its connection:

```php
public function up(Schema $schema): void
{
    $platform = $this->connection->getDatabasePlatform();

    foreach ($platform->getCreateTablesSQL(OrganizationTables::for($platform)->tables()) as $sql) {
        $this->addSql($sql);
    }
}
```

`DbalOrganizationStore::configureSchema()` adds the tables to a schema that lacks them.

## A Flex recipe

The recipe in `recipe/0.1` registers the bundle and writes `config/packages/phil_organizations.yaml` with the key of
the invitations read from `ORGANIZATIONS_INVITATION_KEY`.
