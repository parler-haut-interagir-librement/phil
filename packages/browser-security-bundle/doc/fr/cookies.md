<!-- phil-dev source-sha256=e23a033223220d1ab403360509a7fd9c88773cc4b153bdeb1c6e49d6df3f271b -->
# Cookies signés

Un cookie signé ne peut pas être changé par le navigateur : l'application lit et écrit des valeurs en clair, le bundle
ajoute la signature à la sortie et la vérifie à l'entrée. Un cookie dont la signature échoue est retiré de la requête,
comme si le navigateur ne l'avait pas envoyé.

```yaml
phil_browser_security:
    signed_cookies:
        names: [theme, cart_token]
        secret: '%kernel.secret%'
        previous_secrets: []
```

La signature est un HMAC-SHA-256 du nom et de la valeur sous une clé dérivée du secret, pour que la valeur d'un cookie
ne passe pas pour celle d'un autre. Elle prouve qui a écrit la valeur ; le navigateur peut toujours la lire, donc rien de
confidentiel n'y a sa place.

Pour remplacer le secret, déplacez l'ancien dans `previous_secrets` : il vérifie encore, et seul le nouveau signe.
Retirez-le une fois expiré le cookie à la plus longue durée de vie.

## Ce que Symfony fait déjà

`UriSigner` signe des adresses, et le cookie remember-me porte sa propre signature ; Symfony ne signe aucun autre
cookie. Laissez le cookie de session hors de `names` : sa valeur est déjà un identifiant aléatoire, et l'écouteur de
session le lit avant que celui-ci ne s'exécute.

## Une page

Les cookies sont vérifiés dès que la route est connue, donc seule une route peut changer la liste :

```yaml
beta:
    path: /beta
    controller: App\Controller\BetaController
    defaults:
        _browser_security:
            add: { signed-cookies: [beta_flag] }
```

`add`, `replace` et `remove: [signed-cookies]` s'appliquent tous. Un écouteur de `kernel.request` au-dessus de la
priorité 30 voit la valeur signée.
