# Reference

## `Phil\SamlSpBundle\Application`

| Type | Role |
|---|---|
| `IdentityProviders` | The identity providers by key, their settings, metadata and service provider; `reset()` between requests. |
| `IdentityProviderSettings`, `ServiceProviderSettings` | What the configuration says of an identity provider and of the service provider. |
| `SamlLogin` | Starts a sign-in and completes it from the response; `localPath()`. |
| `LoginStart`, `CompletedLogin` | A sign-in started, and completed. |
| `SamlUsers` | The user for an identity: an account of `SamlAccounts`, or a `SamlUser`; `roles()`. |
| `SamlUser` | The user of the session when the application has no accounts. |
| `SamlLogout`, `LogoutOutcome` | Single logout, and what a logout message leads to. |
| `Contract\SamlAccounts` | The port of the accounts of the application. |
| `Contract\EndpointUrls` | The port of the addresses of the routes. |
| `Exception\AccountRefused` | A person without an account the identity provider may not create. |
| `Event\SamlLoginSucceeded`, `Event\SamlAccountCreated`, `Event\SamlLogoutReceived`, `Event\SamlResponseRefused` | The events. |

## `Phil\SamlSpBundle\Infrastructure`

| Type | Role |
|---|---|
| `Security\SamlAuthenticator` | The authenticator of the assertion consumer service; `COOKIE`. |
| `Security\SamlUserProvider` | Refreshes the `SamlUser` of the session; service `phil_saml_sp.user_provider`. |
| `Security\SingleLogoutListener` | Carries Symfony's logout on to the identity provider. |
| `Security\SamlAttributes` | The attributes of the token: identity provider, name identifier and format, session index, target. |
| `Controller\SamlController` | The metadata, login, assertion consumer service and logout service routes. |
| `Console\CheckCommand` | `phil:saml:check`. |
| `Console\PurgeAssertionsCommand` | `phil:saml:purge-assertions`, registered with the table of the accepted assertions; `NAME`. |
| `Routing\RoutedEndpoints` | The addresses of the routes, from the router; the route names. |
| `Http\OutboundResponder` | The redirect or the self-posting page of a message. |
| `DependencyInjection\SamlServices` | The services of the configuration. |
| `DependencyInjection\ReplayGuardPass` | Chooses the store of the accepted assertions and refuses a local cache pool in `prod`; `PARAMETER`. |
| `DependencyInjection\PoolLocality` | Whether a cache pool stays on each server, from its definition. |

## Routes

`phil_saml_sp_metadata`, `phil_saml_sp_login`, `phil_saml_sp_acs`, `phil_saml_sp_slo`, under `/saml/{idp}` with the
recipe; see [Installation](installation.md#the-routes).

## Assets

`assets/src/auto_submit_controller.js`: the Stimulus controller `phil--saml-sp-bundle--auto-submit`.
