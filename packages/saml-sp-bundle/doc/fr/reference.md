<!-- phil-dev source-sha256=b2b8b47b2376d56e78414b161e741e78b103994edd0f1f2d212da77ca7b1d7f4 -->
# Référence

## `Phil\SamlSpBundle\Application`

| Type | Rôle |
|---|---|
| `IdentityProviders` | Les fournisseurs d'identité par clé, leurs réglages, leurs métadonnées et leur fournisseur de service ; `reset()` entre les requêtes. |
| `IdentityProviderSettings`, `ServiceProviderSettings` | Ce que la configuration dit d'un fournisseur d'identité et du fournisseur de service. |
| `SamlLogin` | Lance une connexion et la termine à partir de la réponse ; `localPath()`. |
| `LoginStart`, `CompletedLogin` | Une connexion lancée, et terminée. |
| `SamlUsers` | L'utilisateur d'une identité : un compte de `SamlAccounts`, ou un `SamlUser` ; `roles()`. |
| `SamlUser` | L'utilisateur de la session quand l'application n'a pas de comptes. |
| `SamlLogout`, `LogoutOutcome` | La déconnexion unique, et ce à quoi mène un message de déconnexion. |
| `Contract\SamlAccounts` | Le port des comptes de l'application. |
| `Contract\EndpointUrls` | Le port des adresses des routes. |
| `Exception\AccountRefused` | Une personne sans compte que le fournisseur d'identité ne peut pas créer. |
| `Event\SamlLoginSucceeded`, `Event\SamlAccountCreated`, `Event\SamlLogoutReceived`, `Event\SamlResponseRefused` | Les événements. |

## `Phil\SamlSpBundle\Infrastructure`

| Type | Rôle |
|---|---|
| `Security\SamlAuthenticator` | L'authentificateur du service de consommation d'assertions ; `COOKIE`. |
| `Security\SamlUserProvider` | Rafraîchit le `SamlUser` de la session ; service `phil_saml_sp.user_provider`. |
| `Security\SingleLogoutListener` | Poursuit la déconnexion de Symfony jusqu'au fournisseur d'identité. |
| `Security\SamlAttributes` | Les attributs du jeton : fournisseur d'identité, identifiant de nom et format, index de session, destination. |
| `Controller\SamlController` | Les routes des métadonnées, de la connexion, du service de consommation d'assertions et du service de déconnexion. |
| `Console\CheckCommand` | `phil:saml:check`. |
| `Console\PurgeAssertionsCommand` | `phil:saml:purge-assertions`, enregistrée avec la table des assertions acceptées ; `NAME`. |
| `Routing\RoutedEndpoints` | Les adresses des routes, à partir du routeur ; les noms des routes. |
| `Http\OutboundResponder` | La redirection ou la page qui se poste d'elle-même d'un message. |
| `DependencyInjection\SamlServices` | Les services de la configuration. |
| `DependencyInjection\ReplayGuardPass` | Choisit le magasin des assertions acceptées et refuse une réserve de cache locale en `prod` ; `PARAMETER`. |
| `DependencyInjection\PoolLocality` | Si une réserve de cache reste sur chaque serveur, d'après sa définition. |

## Routes

`phil_saml_sp_metadata`, `phil_saml_sp_login`, `phil_saml_sp_acs`, `phil_saml_sp_slo`, sous `/saml/{idp}` avec la
recette ; voir [Installation](installation.md#les-routes).

## Ressources

`assets/src/auto_submit_controller.js` : le contrôleur Stimulus `phil--saml-sp-bundle--auto-submit`.
