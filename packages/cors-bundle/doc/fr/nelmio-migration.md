<!-- phil-dev source-sha256=c5c2b26efe0d727bc60e004d0cabf813d885ef3a42e089b32e25bdf15bcb2648 -->
# Venir de NelmioCorsBundle

Les options de `nelmio_cors` correspondent à celles de `phil_cors` comme suit. La politique d'un chemin est autonome :
il n'y a pas de section `defaults` à fusionner, donc chaque chemin énumère ce qu'il autorise.

| `nelmio_cors` | `phil_cors` |
|---|---|
| `paths` avec des clés `^/api/` | `paths`, les mêmes clés : des expressions régulières sur le chemin, la première qui correspond l'emporte |
| `allow_origin` | `allow_origins` pour les origines exactes, `allow_origin_patterns` pour les expressions régulières |
| `origin_regex: true` | Déplacez les expressions dans `allow_origin_patterns`, chacune ancrée par `^` et `$` |
| `allow_methods` | `allow_methods` ; `GET`, `HEAD` et `POST` par défaut |
| `allow_headers` | `allow_headers` |
| `expose_headers` | `expose_headers` |
| `allow_credentials` | `allow_credentials` |
| `max_age` | `max_age`, ou `null` pour n'en envoyer aucun |
| `allow_private_network` | `allow_private_network` |
| `hosts` | Pas une option : créez un alias de `PolicyResolver` vers un service qui lit l'hôte, comme le montre [Points d'extension](extension-points.md) |
| `forced_allow_origin_value` | Pas une option : le bundle répond toujours l'origine de la requête, ou `*` pour toute origine sans identifiants de connexion |
| `skip_same_as_origin` | Inutile : une requête de même origine reçoit les mêmes en-têtes, que les navigateurs ignorent |

La variable d'environnement `CORS_ALLOW_ORIGIN` de la recette garde son sens, une expression régulière : donnez-la à
`allow_origin_patterns`.

```yaml
# before
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: [GET, OPTIONS, POST, PUT, PATCH, DELETE]
        allow_headers: [Content-Type, Authorization]
        expose_headers: [Link]
        max_age: 3600
    paths:
        '^/': null
```

```yaml
# after
phil_cors:
    paths:
        '^/':
            allow_origin_patterns: ['%env(CORS_ALLOW_ORIGIN)%']
            allow_methods: [GET, POST, PUT, PATCH, DELETE]
            allow_headers: [Content-Type, Authorization]
            expose_headers: [Link]
            max_age: 3600
```

`OPTIONS` n'a pas besoin d'être énuméré : le bundle répond lui-même au préambule.

Certaines configurations que l'autre bundle accepte arrêtent la construction ici : `*` avec `allow_credentials`, un
motif d'origine non ancré, l'origine `null`, et une origine avec un chemin ou une barre oblique finale.
[Configuration](configuration.md#ce-qui-arrête-la-construction) les énumère avec leurs raisons.
