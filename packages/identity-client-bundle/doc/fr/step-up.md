<!-- phil-dev source-sha256=ace8e883a7ce4b6d64781720d2032ae8b4ae50c8de38d027e170c18b17d0545a -->
# Authentification renforcée

Une application peut demander une authentification plus forte ou plus récente sur les pages qui en ont besoin.

## Niveaux d'assurance

```yaml
phil_identity_client:
    elevation:
        levels:              # weakest first
            password: { acr: 'urn:example:password' }
            two_factor: { acr: 'urn:example:two-factor' }
            confirm: { acr: 'urn:example:two-factor', max_age: 300 }
            recent: { max_age: 900 }
```

Chacun s'accorde sous la forme `OIDC_ASSURANCE:<name>` :

```php
#[IsGranted('OIDC_ASSURANCE:two_factor')]
public function payout(): Response
```

Un niveau est atteint par sa classe ou par une classe plus forte sur l'échelle. Un `acr` que l'échelle ne liste pas
n'atteint rien. `max_age` compare la dernière preuve d'authentification à l'horloge.

## Le niveau exigé

`elevation.required_level` nomme un niveau que toute connexion doit atteindre :

```yaml
phil_identity_client:
    elevation:
        required_level: password
        levels:
            password: { acr: 'urn:example:password' }
            two_factor: { acr: 'urn:example:two-factor' }
```

Le niveau a besoin d'un `acr`. Chaque requête d'autorisation demande sa classe et les plus fortes dans `acr_values` ;
une authentification renforcée vers un niveau plus faible, ou vers un niveau sans classe, demande plutôt les classes
exigées. Le fournisseur peut tout de même répondre avec une classe plus faible : l'`acr` retourné est vérifié, et une
connexion sous le niveau échoue avec `AssuranceLevelNotReachedException` et aboutit à `paths.failure`. Un `acr` que
l'échelle ne liste pas, ou aucun, n'atteint rien.

Une session sous le niveau, comme une session commencée avant que l'exigence soit relevée, prend fin à sa requête
suivante. `LocalSessionEnded` le signale avec `LocalSessionEndReason::AssuranceNotReached`.

## Ce que voit l'utilisateur

`AssuranceVoter` refuse et demande au pare-feu une nouvelle authentification. Le navigateur va chez le fournisseur avec
des `acr_values` qui nomment le niveau et les classes plus fortes, et `prompt=login`. Au retour, il aboutit à la page
qui l'a demandé.

Un fournisseur peut répondre sans atteindre le niveau. `ElevationAttempts` l'enregistre, et la page répond par un `403`
pendant `elevation.retry_after` secondes au lieu de renvoyer le navigateur en boucle. `ElevationRefused` le signale.

## Le temps seul

`IS_AUTHENTICATED_RECENTLY` et `IS_AUTHENTICATED_VERY_RECENTLY` de Symfony fonctionnent sans changement, sur le
`auth_time` du jeton d'identité. Leurs fenêtres sont `security.recent_authentication_lifetime` et
`security.very_recent_authentication_lifetime`.

`prompt=login` est une demande. `elevation.max_age` ajoute `max_age` à chaque requête d'autorisation, et
l'authentificateur refuse alors un jeton d'identité dont le `auth_time` est trop ancien.

## Scripts

Une requête qui n'est pas une navigation de page (un `XMLHttpRequest`, ou un `Accept` autre que HTML) ne peut pas suivre
une redirection vers un fournisseur. Elle reçoit le défi de la RFC 9470 :

```http
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer error="insufficient_user_authentication", error_description="A stronger authentication is required.", acr_values="urn:example:two-factor", max_age="300"
```
