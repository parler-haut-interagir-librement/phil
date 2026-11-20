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
listener; Twig, the CSRF protection, the router and Symfony Security bring the administration pages of the units;
`phil/privacy-bundle` and `phil/access-bundle` bring their integrations when they are installed.

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

The default store keeps the organizations and their units in eight tables of the `default` DBAL connection, named in
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

`DbalOrganizationStore::configureSchema()` adds the tables to a schema that lacks them, and
`OrganizationTables::upgrade()` gives the statements that create the tables a database lacks, which is how the
migration of the recipe works:

```php
public function up(Schema $schema): void
{
    $tables = OrganizationTables::for($this->connection->getDatabasePlatform());

    foreach ($tables->upgrade($schema, $this->connection->createSchemaManager()->createComparator()) as $statement) {
        $this->addSql($statement);
    }
}
```

On a database of 0.1 it creates `phil_organization_unit` and `phil_organization_unit_member`; on an empty one, every
table. It only adds tables. `OrganizationTables::downgrade()` drops the two tables of the units, for the way back.

## The administration pages

The pages of the units draw the recipes `Badge`, `Button`, `Input`, `NativeSelect`, `Table` and `Tree` of the PHIL UX
kit, which the Symfony UX Toolkit installs in the application:

```sh
php bin/console ux:install badge button input native-select table tree --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Their routes are imported behind the firewall of the application:

```yaml
# config/routes/phil_organizations.yaml
phil_organizations_units:
    resource: '@PhilOrganizationsBundle/config/routes/units.php'
    prefix: /admin/organizations
```

[Units](units.md#administration-pages) describes them.

## A Flex recipe

The recipe in `recipe/0.2` registers the bundle, writes `config/packages/phil_organizations.yaml` with the key of the
invitations read from `ORGANIZATIONS_INVITATION_KEY`, imports the administration pages of the units under
`/admin/organizations`, and copies to `migrations/` the migration that adds the tables of the units, or every table to
an empty database. The recipe in `recipe/0.1` serves the versions 0.1.
