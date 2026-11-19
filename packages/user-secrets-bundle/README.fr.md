<!-- phil-dev source-sha256=0a872ecb5c931b7894e5f75a519401dcaf3f756ffe06d89a99b27565a3408cdd -->
# phil/user-secrets-bundle

**Les clés que les gens prêtent à votre application, gardées comme des clés : scellées, à eux seuls, et disparues
quand ils partent.**

phil/user-secrets-bundle conserve les identifiants de connexion qu'une personne confie à une application Symfony pour
un service tiers : une clé d'API, un jeton OAuth avec son jeton d'actualisation. Chaque valeur est scellée par
chiffrement par enveloppe au moyen du composant KeyManagement de Symfony : une nouvelle clé de données, enveloppée par
une clé maîtresse d'un KMS, celui de l'application ou un KMS local `sodium://`. Le propriétaire, le nom et le service
sont scellés à côté de la valeur et authentifiés avec elle : un texte chiffré copié dans une autre ligne ne s'ouvre
pas.
Chaque valeur scellée commence par un en-tête qui nomme son format et son algorithme, authentifié avec elle, et les
lecteurs ouvrent chaque valeur selon cet en-tête. Seule une tâche exécutée pour le propriétaire lit un secret, et elle
dit dans quel but. Un jeton OAuth expiré est actualisé par le fournisseur d'actualisation de l'application avant d'être
remis, un processus à la fois sous un verrou, et enregistré par un compare-and-swap. Le propriétaire liste ses secrets
sans leurs valeurs, en révoque un — sa valeur effacée, la ligne gardée pour montrer quand —, ou le supprime, et chaque
secret part avec le compte par `phil/privacy-bundle` ; un jeton OAuth qui part est aussi révoqué auprès de son service,
par le révocateur de l'application. Chaque événement va aux crochets d'audit de l'application, jamais avec une valeur.

Il s'adresse aux applications Symfony qui agissent pour leurs membres auprès d'autres services : un outil d'écriture
qui appelle un modèle de langage avec la clé du membre, un site qui publie dans le dépôt ou le stockage du membre, un
planificateur qui synchronise la nuit le calendrier d'un membre.

C'est un bundle bâti sur le composant KeyManagement de Symfony derrière un port, `ext-sodium`, Doctrine ORM et PSR-20 ;
`phil/privacy-bundle`, `symfony/lock` et `symfony/console` sont facultatifs. Le composant KeyManagement est
expérimental dans Symfony 8.2, hors de la promesse de rétrocompatibilité de Symfony : un adaptateur du bundle l'appelle.
Les clés maîtresses changent sans interruption de service : ajoutez une clé, faites-en la clé courante, lancez
`phil:user-secrets:rotate`, puis retirez l'ancienne une fois que `phil:user-secrets:rotate --dry-run` ne compte plus
aucun secret.

La conception suit les couches du monodépôt. `Domain` contient la ligne `StoredSecret`, les identifiants de connexion
et les types de valeurs, et le port du stockage. `Application` contient `UserSecrets`, `KeyRotation`, le port
`EnvelopeCipher`, l'enveloppe, la piste d'audit et les ports `TokenRefresher`, `TokenRevoker`, `RefreshLock` et
`SecretAuditor`. `Infrastructure` contient l'adaptateur KeyManagement, les stockages Doctrine et en mémoire, le verrou
sur le composant Lock de Symfony, le module de vie privée, l'auditeur journaliseur, les commandes et le câblage des
services.

## État

| Partie | État |
|---|---|
| Les clés d'API et les jetons OAuth scellés par chiffrement par enveloppe avec Symfony KeyManagement, liés à leur ligne | Implémenté et testé |
| Un en-tête qui nomme le format et l'algorithme de chaque valeur scellée, authentifié avec elle ; les valeurs de l'ancien format se lisent encore | Implémenté et testé |
| Le client par défaut du composant, un client nommé par son service, ou un client `sodium://` local ; les échecs du KMS enveloppés dans les exceptions du bundle | Implémenté et testé |
| La rotation des clés par commande, entre clés maîtresses et hors de l'ancien format | Implémentée et testée |
| La lecture seulement par une tâche du propriétaire, avec son but | Implémentée et testée |
| Les jetons OAuth actualisés par les fournisseurs d'actualisation de l'application avant leur expiration | Implémenté et testé |
| Une actualisation à la fois sous le composant Lock de Symfony, et un compare-and-swap sur la ligne, avec ou sans lui | Implémenté et testé |
| La liste sans valeurs, la révocation, la suppression | Implémentées et testées |
| L'exportation et l'effacement avec le compte par `phil/privacy-bundle` | Implémentés et testés |
| Les crochets d'audit, et un auditeur qui écrit dans un journaliseur PSR-3 | Implémentés et testés |
| Le stockage Doctrine, et un stockage en mémoire pour les tests | Implémentés et testés |
| La révocation auprès du service (RFC 7009) par les révocateurs de l'application, après la révocation, la suppression et l'effacement, un échec étant journalisé et audité | Implémentée et testée |
| Les formulaires et les écrans du propriétaire | Laissés à l'application, qui les construit sur `UserSecrets` : [ce qu'ils appellent](doc/fr/secrets.md#écrans-du-propriétaire) |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement des bundles, le KMS et sa clé maîtresse, la table. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_user_secrets`. |
| [Stocker et lire les secrets](doc/fr/secrets.md) | `UserSecrets` : stocker, lister, lire par une tâche, révoquer, supprimer, effacer ; la révocation auprès du service ; les écrans du propriétaire. |
| [Chiffrement et rotation des clés](doc/fr/encryption.md) | Ce qui est scellé, le format scellé et son en-tête de format et d'algorithme, le KMS et celui, local, de `sodium://`, la rotation des clés, ce contre quoi le chiffrement ne protège pas. |
| [Jetons OAuth](doc/fr/oauth-tokens.md) | Les jetons, leur expiration, les actualisations concurrentes et le verrou, l'écriture d'un `TokenRefresher` et d'un `TokenRevoker`. |
| [Audit et vie privée](doc/fr/audit-and-privacy.md) | Les événements, l'écriture d'un `SecretAuditor`, l'auditeur journaliseur, l'exportation et l'effacement avec `phil/privacy-bundle`. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle, et quand. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/user-secrets-bundle
bin/console phil:user-secrets:generate-key     # put the output in USER_SECRETS_KEY_2026
```

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[k2026]=%env(USER_SECRETS_KEY_2026)%'

# config/packages/phil_user_secrets.yaml
phil_user_secrets:
    current_key: k2026
```

```php
$secrets->store($owner, SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of($submittedKey));

// Later, in a task run for that member:
$key = $secrets->apiKey(SecretAccess::forTaskOf($owner, 'draft a chapter'), SecretName::fromString('openai'));
$client->withApiKey($key->key->reveal());
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-sodium` et `ext-openssl`, Symfony 8.2 avec son composant KeyManagement, Doctrine ORM 3.7 ;
facultativement, le composant Lock de Symfony.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
