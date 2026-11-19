<!-- phil-dev source-sha256=a9bf0aad0d7eef242d4f2253a0b06e5a8aef64affa332e006cf0ef927f1c53dd -->
# phil/api-keys-bundle

**Des jetons d'accès pour une API publique, pour des membres, des organisations ou des applications : montrés une fois,
gardés sous forme d'empreinte à clé, limités à des portées, expirables, révocables.**

phil/api-keys-bundle permet aux membres d'une application Symfony de créer des jetons pour son API publique (un script
de déploiement, un tableur, un boîtier domotique) et permet à l'API de savoir qui appelle et dans quel but. Un jeton agit
pour un membre, ou pour un autre type de propriétaire que déclare la configuration, comme une organisation ou une
application, chargé par son propre fournisseur d'utilisateurs et limité aux portées que ce type peut détenir. Un jeton
se lit `acme_3kTMd9qXbW1z_<32 characters><6 characters>`. Il se compose d'un préfixe qui nomme l'application, pour que les
détecteurs de secrets le reconnaissent dans un dépôt; d'un identifiant qui trouve sa ligne; d'un secret tiré de la
source sécurisée de `random_bytes()`; et d'une somme de contrôle CRC-32 qui refuse hors ligne un jeton mal tapé ou
tronqué, avant toute recherche. Le texte est montré une fois, à la création. La table garde seulement le HMAC-SHA-256
du secret sous une clé tirée du coffre de secrets, comparé en temps constant, écrit avec le nom de son algorithme et sa version. Les clés forment une liste ordonnée : la
première calcule l'empreinte, chacune la vérifie, de sorte que la clé peut être renouvelée sans couper les jetons émis
sous la précédente.

Chaque jeton porte des portées que déclare la configuration, avec une description pour les développeurs et un libellé
traduisible pour les membres. Chaque jeton expire, dans une durée de vie maximale que fixe la configuration. Un jeton
est révoqué sur-le-champ (un seul, ou tous ceux d'un propriétaire), ou renouvelé : un successeur reçoit les mêmes portées, et l'ancien jeton est révoqué ou
gardé pendant une période de grâce, le temps que le client change de jeton. Sa dernière utilisation est écrite au plus
une fois par intervalle, avec l'adresse du client seulement telle que l'anonymise `phil/ip-anonymizer`.

Un pare-feu sans état donne à l'authentificateur `access_token` de Symfony le gestionnaire de jetons du bundle :
`Authorization: Bearer <token>` ou un autre en-tête, l'utilisateur chargé par le propriétaire du jeton au moyen du fournisseur de son type, les portées comme rôles et
attributs, un *voter* et `#[RequiresScope('projects:write')]` sur les contrôleurs. Un jeton refusé reçoit la réponse
`401` avec le défi de la RFC 6750, sous forme de détails de problème RFC 9457 quand `phil/api-contract-bundle` est installé; une portée
manquante reçoit `403 insufficient_scope`. Les limites de débit et les quotas comptent par jeton au moyen de
`phil/rate-limit-bundle` et de `phil/usage-meter-bundle`, avec leurs en-têtes `RateLimit`. Des commandes créent,
listent, révoquent et purgent les jetons, et une page facultative permet aux membres de gérer les leurs, et ceux d'une organisation ou d'une application quand
les *voters* de l'application le permettent. Un point de terminaison facultatif reçoit les jetons que l'analyse de secrets de GitHub trouve dans des dépôts publics, vérifie la
signature de GitHub et les révoque. Les événements portent seulement des identifiants, pour une piste d'audit.

C'est un bundle bâti sur Symfony Security, Doctrine ORM et PSR-20. La console, le composant de formulaire, Twig,
`phil/api-contract-bundle`, `phil/rate-limit-bundle`, `phil/ip-anonymizer`, ainsi que le client HTTP et le cache de
Symfony pour le point de terminaison de l'analyse de secrets, sont facultatifs. Ses services ne gardent aucun état
entre les requêtes et conviennent à un *worker* de longue durée; le stockage en mémoire est vidé entre les requêtes.

La conception suit les couches du monodépôt. `Domain` contient le format du jeton, le propriétaire, la ligne `ApiToken`, le
port du stockage et les événements. `Application` contient `ApiTokens`, le calculateur d'empreinte, le catalogue des
portées, les types de propriétaires et le port de l'anonymiseur d'adresse. `Infrastructure` contient les stockages Doctrine et en mémoire, le gestionnaire de jetons
et l'écouteur des rôles, le *voter* et l'attribut, le point de terminaison de l'analyse de secrets de GitHub, les
intégrations, les commandes, le formulaire et la page, et le câblage des services.

## État

| Partie | État |
|---|---|
| Format de jeton avec préfixe, identifiant, secret et somme de contrôle; lecteur strict, éprouvé par *fuzzing* | Implémenté et testé |
| Empreinte à clé seulement, qui nomme son algorithme et sa version, comparée en temps constant; texte montré une fois; clés ordonnées pour le renouvellement | Implémenté et testé |
| Propriétaires typés (membres, organisations, applications) chargés par leurs propres fournisseurs d'utilisateurs, portées par type, révocation de tous les jetons d'un propriétaire | Implémenté et testé |
| Portées déclarées dans la configuration, descriptions et libellés traduisibles | Implémenté et testé |
| Expiration obligatoire dans une durée de vie maximale; révocation; renouvellement avec grâce; purge | Implémenté et testé |
| Dernière utilisation écrite une fois par intervalle, adresse anonymisée ou non gardée | Implémenté et testé |
| Gestionnaire de jetons pour l'authentificateur `access_token` de Symfony, rôles et attributs de portée, *voter*, `#[RequiresScope]`, défis de la RFC 6750 | Implémenté et testé |
| Point de terminaison de l'analyse de secrets de GitHub : signature vérifiée, jetons divulgués révoqués; désactivé par défaut | Implémenté et testé |
| Détails de problème avec `phil/api-contract-bundle`, limites de débit par jeton avec `phil/rate-limit-bundle` | Implémenté et testé |
| Commandes; page des membres et page des jetons d'un autre propriétaire derrière un *voter*, en anglais et en français | Implémenté et testé |
| Stockage Doctrine et stockage en mémoire, vidé entre les requêtes | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, la clé, la table, le pare-feu. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_api_keys`. |
| [Jetons](doc/fr/tokens.md) | Le format, ce qui est stocké, la création, le renouvellement, la révocation, la purge, la dernière utilisation. |
| [Authentification et portées](doc/fr/authentication.md) | Le pare-feu, les rôles et les attributs, le *voter*, `#[RequiresScope]`, les réponses. |
| [Analyse de secrets](doc/fr/secret-scanning.md) | Le point de terminaison où GitHub envoie les jetons divulgués : l'activer, la signature, la révocation, les clés. |
| [La page des membres](doc/fr/members-page.md) | Les routes, le formulaire, les gabarits, les traductions. |
| [Intégrations](doc/fr/integrations.md) | Les quotas, les détails de problème, les adresses anonymisées, les événements pour une piste d'audit. |
| [Console](doc/fr/console.md) | Les quatre commandes. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle, et quand. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, ses cibles de *fuzzing* et son banc d'essai, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/api-keys-bundle
bin/console secrets:set API_KEYS_HASH_KEY     # 32 random bytes or more
```

```yaml
# config/packages/phil_api_keys.yaml
phil_api_keys:
    prefix: acme
    hash_key: '%env(API_KEYS_HASH_KEY)%'
    scopes:
        projects:read: { description: 'Read the projects', label: 'scope.projects_read' }
        projects:write: { description: 'Create and change projects', label: 'scope.projects_write' }

# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            access_token:
                token_handler: phil_api_keys.token_handler
                token_extractors: [phil_api_keys.token_extractor]
                realm: api
```

```php
#[RequiresScope('projects:read')]
final class ProjectController
{
    #[RequiresScope('projects:write')]
    public function update(): Response
    {
        // ...
    }
}
```

```sh
curl -H "Authorization: Bearer acme_3kTMd9qXbW1z_…" https://example.com/api/projects
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 avec ses composants de sécurité, Doctrine ORM 3.7.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
