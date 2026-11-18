<!-- phil-dev source-sha256=1de0a4a02b5b624c51989aa97a729e8c5ba952cc9e0076bcb0d2ac3e10a96843 -->
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
`symfony/security-core` le *voter*, `doctrine/orm` l'écouteur de schéma ; `phil/privacy-bundle` et `phil/access-bundle`
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

Le magasin par défaut conserve les organisations dans six tables de la connexion DBAL `default`, nommées dans
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

`DbalOrganizationStore::configureSchema()` ajoute les tables à un schéma qui en est dépourvu.

## Une recette Flex

La recette de `recipe/0.1` enregistre le bundle et écrit `config/packages/phil_organizations.yaml`, avec la clé des
invitations lue dans `ORGANIZATIONS_INVITATION_KEY`.
