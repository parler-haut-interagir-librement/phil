<!-- phil-dev source-sha256=5d0fbdb586bd69d2727d572292200bec85264e960d3227e83ff90ad164964ac5 -->
# Installation

```sh
composer require phil/saml-sp-bundle
```

La recette Flex enregistre `PhilSamlSpBundle`, écrit `config/packages/phil_saml_sp.yaml`, importe les routes sous
`/saml` et ajoute `SAML_SP_CERTIFICATE` et `SAML_SP_PRIVATE_KEY` à `.env` : les chemins de la paire de clés du
fournisseur de service. Sans Flex, enregistrez le bundle dans `config/bundles.php` et copiez les deux fichiers de
`recipe/0.1/config`.

## La paire de clés

Le fournisseur de service signe ses requêtes et ses messages de déconnexion, et déchiffre les assertions qui lui sont
envoyées, avec une paire de clés RSA. Faites-en une par environnement, hors du dépôt :

```sh
mkdir -p config/saml
openssl req -x509 -newkey rsa:3072 -sha256 -nodes -days 1095 \
    -subj "/CN=app.example.org SAML" -keyout config/saml/sp.key -out config/saml/sp.crt
chmod 600 config/saml/sp.key
echo '/config/saml/' >> .gitignore
```

Le certificat est volontairement auto-signé : SAML fait confiance au certificat publié dans les métadonnées, non à une
chaîne. En production, gardez la clé dans un magasin de secrets et pointez `SAML_SP_PRIVATE_KEY` vers le fichier qu'il
monte. Avant que le certificat n'expire, `phil:saml:check` le dit ; publiez aussi le suivant dans `encryption` (voir
[Configuration](configuration.md#renouvellement-des-clés)).

## Le pare-feu

L'authentificateur s'exécute dans le pare-feu qui couvre `/saml` :

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

`phil_saml_sp.user_provider` garde le `SamlUser` de la session. Une application qui a ses propres comptes implémente
`SamlAccounts` et nomme plutôt son propre fournisseur d'utilisateurs (voir [Comptes et rôles](accounts.md)).

## Les routes

| Route | Chemin | Méthode | Rôle |
|---|---|---|---|
| `phil_saml_sp_metadata` | `/saml/{idp}/metadata` | GET | Les métadonnées du fournisseur de service pour ce fournisseur d'identité. |
| `phil_saml_sp_login` | `/saml/{idp}/login?target=/path` | GET | Envoie la personne au fournisseur d'identité ; `target` est un chemin local. |
| `phil_saml_sp_acs` | `/saml/{idp}/acs` | POST | Le service de consommation d'assertions, exécuté par l'authentificateur. |
| `phil_saml_sp_slo` | `/saml/{idp}/slo` | GET, POST | Le service de déconnexion. |

`{idp}` est la clé d'un fournisseur d'identité dans la configuration. Le préfixe est celui de la recette : changez-le
dans `config/routes/phil_saml_sp.yaml` avant de donner les adresses aux fournisseurs d'identité, puisqu'elles font
partie des métadonnées.

## La table des assertions acceptées

Avec DoctrineBundle et sa connexion `default` — ou celle que nomme `replay_guard.connection` —, le bundle garde les
assertions acceptées dans la table `phil_saml_used_assertion` (`replay_guard.table`). Créez-la avec une migration de
l'application ; `DbalReplayGuard::configureSchema()` l'ajoute à un schéma :

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

La table et ses colonnes sont décrites dans le guide du magasin de rejeu de phil/saml-sp. Les lignes expirées restent
jusqu'à leur purge : planifiez `bin/console phil:saml:purge-assertions`, une fois par heure par exemple. Sans connexion,
les assertions acceptées vont dans `cache_pool`, qui doit alors être partagée par chaque serveur en production (voir
[Sécurité](security.md#rejeu)).

## Stimulus

Sur la liaison HTTP-POST, la connexion répond par une page dont le formulaire porte la requête ; le contrôleur Stimulus
`phil--saml-sp-bundle--auto-submit` la poste aussitôt. Sans StimulusBundle, la page affiche un bouton « Continue ». Avec
AssetMapper, `composer require symfony/stimulus-bundle` enregistre le contrôleur à partir de `assets/package.json`.
