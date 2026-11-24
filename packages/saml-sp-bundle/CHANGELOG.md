# Changelog

All notable changes to `phil/saml-sp-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-24

### Added

- The configuration `phil_saml_sp`: the service provider — entity ID, signing and encryption key pairs in PEM, name
  identifier format, signed requests —, the security options — clock skew, age of a response, SHA-1, smallest RSA key,
  localhost, browser binding, cookie —, the targets, the cache pool, the store of the accepted assertions, the clock,
  the HTTP client, and the identity providers by key, each with its metadata from an address, a file or XML, the
  certificates it must be signed with, a preset of claim names (Microsoft Entra ID, Okta, LDAP), its attribute names,
  group roles, default roles, and what its responses must be.
- `IdentityProviders`: the identity providers, their metadata through the cache of phil/saml-sp, the service provider
  each one sees, its routes as entity ID by default; forgotten between the requests of a worker.
- The routes of each identity provider: the metadata of the service provider, the login with a local target, the
  assertion consumer service and the logout service.
- `SamlLogin` and `SamlAuthenticator`: a sign-in remembered under its ID, which travels as the relay state, bound by
  a `Secure`, `HttpOnly`, `SameSite=None` cookie to the browser that started it, completed by the checks of
  phil/saml-sp, the person sent back to a local path only; a refusal answers 403 with no detail.
- `SamlAccounts`, `SamlUsers`, `SamlUser` and `SamlUserProvider`: the account of the application found or created at
  the first sign-in, or a user of the session with the roles its groups give; no SCIM.
- `SamlLogout`, `SingleLogoutListener` and the logout service: Symfony's logout carried on to the identity provider,
  its response checked against the request, and the logout of the identity provider ending the session of the browser
  and answered, every message signed.
- The events `SamlLoginSucceeded`, `SamlAccountCreated`, `SamlLogoutReceived` and `SamlResponseRefused`.
- The accepted assertions in the table of `DbalReplayGuard` on the Doctrine DBAL connection when the application has
  one, `CacheReplayGuard` on the cache pool otherwise, by `replay_guard` (store, connection, table);
  `phil:saml:purge-assertions`, which deletes the expired rows, for a scheduled task.
- In the `prod` environment, a cache pool local to each server — APCu, array, filesystem, system, `cache.app` by
  default — refused as the store of the accepted assertions when the container is built, with what to do, unless
  `single_server` says the application runs on one server.
- `phil:saml:check`: the metadata of each identity provider fetched again, and the expiry of every certificate.
- The page of the HTTP-POST binding, without inline script, and its Stimulus controller; the Flex recipe and its test.
- Unit tests and a test application that signs in, refuses a replay and another browser, and signs out both ways.
- The documentation in `doc/`, with guides for Microsoft Entra ID and Okta, indexed by the README; `CONTRIBUTING.md`,
  `SECURITY.md` and `CODE_OF_CONDUCT.md`.
