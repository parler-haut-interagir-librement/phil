<!-- phil-dev source-sha256=bb0ab8451b8881327b05722b1207df667904965d363d4d8e3b7a9f8636764e42 -->
# HTTPS

## Ce que Symfony fait déjà

`schemes: [https]` sur une route et `requires_channel: https` sur une règle d'accès envoient une requête en clair vers
HTTPS ; ils restent les bons outils pour une route ou une zone, et continuent de fonctionner à côté du bundle. Aucun des
deux n'écrit la règle pour un site entier ni n'envoie `Strict-Transport-Security`, les deux choses qu'ajoute le bundle.
L'adresse de la requête vient de `Request::isSecure()`, donc `framework.trusted_proxies` s'applique.

## La redirection

```yaml
phil_browser_security:
    https:
        redirect:
            enabled: ~            # null follows kernel.debug: on when it is off
            status: 308           # 301, 302, 307 or 308
            port: 443
            exclude_paths: [/health]
```

Une requête en clair est redirigée dès que sa route est connue, et avant que le pare-feu n'en lise quoi que ce soit. Les
préfixes de `exclude_paths` sont servis des deux façons, pour une vérification de santé qu'un répartiteur de charge
demande en HTTP. Une adresse qui ne correspond à aucune route reçoit un 404 avant que la redirection n'ait son mot à
dire.

## Strict-Transport-Security

```yaml
phil_browser_security:
    https:
        strict_transport_security:
            enabled: ~            # null follows kernel.debug: on when it is off
            max_age: 31536000
            include_subdomains: true
            preload: false
```

L'en-tête ne va que sur les réponses aux requêtes HTTPS. `preload` demande `include_subdomains` et un `max_age` d'un an
ou plus : toute autre combinaison fait échouer la construction. Les valeurs par défaut — un an, sous-domaines compris —
sont celles de l'OWASP Application Security Verification Standard. Un navigateur retient cet en-tête pendant `max_age`
secondes : sur un site qui ne l'a jamais envoyé, commencez plus bas et augmentez par étapes, et réglez
`include_subdomains: false` tant qu'un sous-domaine répond encore en HTTP simple.

## Une page

Le nom `https-redirect` agit avant que le contrôleur ne soit connu, donc seule une route peut le changer :

```yaml
legacy_callback:
    path: /callback
    controller: App\Controller\CallbackController
    defaults:
        _browser_security:
            remove: [https-redirect]     # or replace: { https-redirect: true } to force it when it is off
```

## Derrière un mandataire

Une application derrière un mandataire ou un répartiteur de charge qui termine TLS doit le déclarer dans
`framework.trusted_proxies`, avec `trusted_headers` qui liste `x-forwarded-proto`. Sans cela, chaque requête semble en
clair, et la redirection envoie le navigateur en boucle.
