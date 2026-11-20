<!-- phil-dev source-sha256=622bbabb66bbf2140269232bdfead35e5a4779a1c8400abd0e9d3a99021a495e -->
# Installation

```sh
composer require phil/organizations-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php` :

```php
Phil\OrganizationsBundle\PhilOrganizationsBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente avec `ext-filter` et `ext-mbstring`, Symfony 8.2 ou une version
plus récente et Doctrine DBAL 4.5 ou une version plus récente. `symfony/console` apporte les commandes,
`symfony/security-core` le *voter*, `doctrine/orm` l'écouteur de schéma ; Twig, la protection CSRF, le routeur et la
sécurité de Symfony apportent les pages d'administration des unités ; `phil/privacy-bundle` et `phil/access-bundle`
apportent leurs intégrations quand ils sont installés.

## La clé des invitations

Le jeton d'une invitation n'est conservé que sous la forme de son HMAC-SHA-256, calculé avec une clé d'au moins 32
octets. Lisez-la dans le coffre de secrets :

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;' | bin/console secrets:set ORGANIZATIONS_INVITATION_KEY -
```

```yaml
# config/packages/phil_organizations.yaml
phil_organizations:
    invitations:
        hash_key: '%env(ORGANIZATIONS_INVITATION_KEY)%'
```

La configuration est refusée sans elle. Pour faire la rotation de la clé, voir
[Invitations](invitations.md#faire-la-rotation-de-la-clé).

## Les tables

Le magasin par défaut conserve les organisations et leurs unités dans huit tables de la connexion DBAL `default`,
nommées dans
[la référence](reference.md#les-tables). Avec l'ORM, le bundle les ajoute au schéma que l'ORM génère, de sorte que
`doctrine:migrations:diff` écrit la migration qui les crée :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, une migration les construit à partir de `OrganizationTables`, pour la plateforme de sa connexion :

```php
public function up(Schema $schema): void
{
    $platform = $this->connection->getDatabasePlatform();

    foreach ($platform->getCreateTablesSQL(OrganizationTables::for($platform)->tables()) as $sql) {
        $this->addSql($sql);
    }
}
```

`DbalOrganizationStore::configureSchema()` ajoute les tables à un schéma qui en est dépourvu, et
`OrganizationTables::upgrade()` donne les instructions qui créent les tables qu'une base de données n'a pas, ce qui est
le fonctionnement de la migration de la recette :

```php
public function up(Schema $schema): void
{
    $tables = OrganizationTables::for($this->connection->getDatabasePlatform());

    foreach ($tables->upgrade($schema, $this->connection->createSchemaManager()->createComparator()) as $statement) {
        $this->addSql($statement);
    }
}
```

Sur une base de données de la 0.1, elle crée `phil_organization_unit` et `phil_organization_unit_member` ; sur une base
vide, chaque table. Elle ne fait qu'ajouter des tables. `OrganizationTables::downgrade()` supprime les deux tables des
unités, pour le retour en arrière.

## Les pages d'administration

Les pages des unités dessinent les recettes `Badge`, `Button`, `Input`, `NativeSelect`, `Table` et `Tree` du kit PHIL UX,
que le Symfony UX Toolkit installe dans l'application :

```sh
php bin/console ux:install badge button input native-select table tree --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Leurs routes sont importées derrière le pare-feu de l'application :

```yaml
# config/routes/phil_organizations.yaml
phil_organizations_units:
    resource: '@PhilOrganizationsBundle/config/routes/units.php'
    prefix: /admin/organizations
```

[Unités](units.md#pages-dadministration) les décrit.

## Une recette Flex

La recette de `recipe/0.2` enregistre le bundle, écrit `config/packages/phil_organizations.yaml` avec la clé des
invitations lue dans `ORGANIZATIONS_INVITATION_KEY`, importe les pages d'administration des unités sous
`/admin/organizations`, et copie dans `migrations/` la migration qui ajoute les tables des unités, ou chaque table à une
base de données vide. La recette de `recipe/0.1` sert les versions 0.1.
