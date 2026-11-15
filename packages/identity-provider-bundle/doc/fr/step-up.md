<!-- phil-dev source-sha256=e39fa0fe7b0bd75785f7bc1e94216b2b30bd15a6233c23dec435d0c4b27f7d9c -->
# Réauthentification

Les actions sensibles — changer l'adresse ou le mot de passe, ajouter ou retirer une clé d'accès, activer ou désactiver
l'application d'authentification, de nouveaux codes de récupération, retirer un numéro de téléphone, fermer toutes les
autres sessions, délier une identité fédérée — exigent une preuve récente de la méthode la plus forte du compte. Le
bundle bâtit cela sur le mode sudo de Symfony Security 8.2.

## Les preuves

Symfony Security 8.2 enregistre sur le jeton chaque méthode prouvée et le moment (`AuthenticationProofsListener`). Le
bundle indique ses méthodes avec `AuthenticationMethodBadge`, avec les valeurs de la RFC 8176 :

| Connexion | Preuves |
|---|---|
| Un mot de passe, par n'importe quel authentificateur du pare-feu | `pwd` |
| Une clé d'accès | `hwk`, `user`, et `mfa` quand la personne a été vérifiée |
| Un code de l'application d'authentification, un code de récupération | `otp` |
| Un lien de connexion, un fournisseur fédéré | aucune indiquée : `*` (non précisée) |

Une deuxième connexion dans la même session ajoute ses preuves à celles que le jeton détient.

## PHIL_STEP_UP

`StepUpVoter` accorde `PHIL_STEP_UP` quand le compte a prouvé, dans les `step_up.lifetime` secondes :

- `hwk`, `otp` ou `mfa`, pour un compte qui a une clé d'accès ou une application d'authentification ;
- l'une d'elles ou `pwd`, pour un compte qui a un mot de passe et aucune méthode plus forte ;
- n'importe laquelle ou `*`, pour un compte qui se connecte seulement par un fournisseur fédéré, ce qu'il prouve en s'y
  connectant de nouveau.

En cas de refus, le vote appelle `requestReAuthentication('PHIL_STEP_UP')`. Symfony Security garde alors là où la
personne allait et démarre le `re_authentication_entry_point` du pare-feu, le `ReAuthenticationEntryPoint` du bundle,
qui l'envoie à `/reauthenticate` : les méthodes que le compte a, de la plus forte à la plus faible — une clé d'accès, un
code, le mot de passe par le formulaire de connexion de `login_route`, les fournisseurs fédérés auxquels il est lié. La
nouvelle preuve la ramène à l'action.

Une application protège ses propres actions de la même façon :

```php
#[IsGranted('PHIL_STEP_UP')]
public function deleteProject(): Response
```

`step_up.attribute` réglé à `IS_AUTHENTICATED_RECENTLY` fait employer aux actions du bundle l'attribut propre à
Symfony, décidé par le `recent_authentication_lifetime` du pare-feu, quelle qu'ait été la méthode.
