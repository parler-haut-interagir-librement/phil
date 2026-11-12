<!-- phil-dev source-sha256=dfc90a9b4ba3d60b50769ea78fb74c76d94ff62602e338b88b55f5c0c76dbe11 -->
# Politiques

Une politique est une liste de directives, chacune avec ses valeurs, écrites dans l'ordre où elles sont déclarées :

```yaml
phil_browser_security:
    policies:
        default:
            directives:
                default-src: ["'self'"]
                script-src: ["'self'"]
                img-src: ["'self'", 'data:', 'https://images.example.com']
                object-src: ["'none'"]
                frame-ancestors: ["'self'"]
                upgrade-insecure-requests: true
```

```http
Content-Security-Policy: default-src 'self'; script-src 'self'; img-src 'self' data: https://images.example.com; object-src 'none'; frame-ancestors 'self'; upgrade-insecure-requests
```

## Directives

| Sorte | Directives | Valeurs |
|---|---|---|
| Récupération, qui retombe sur `default-src` | `script-src`, `script-src-elem`, `script-src-attr`, `style-src`, `style-src-elem`, `style-src-attr`, `img-src`, `font-src`, `connect-src`, `media-src`, `object-src`, `frame-src`, `child-src`, `worker-src`, `manifest-src` | sources |
| Par défaut | `default-src` | sources |
| Document et navigation | `frame-ancestors`, `form-action`, `base-uri` | emplacements : ni nonce, ni empreinte, ni mot-clé qui relâche les scripts |
| Drapeaux | `upgrade-insecure-requests` | `true` |
| Bac à sable | `sandbox` | `true`, ou ses drapeaux `allow-*` |
| Trusted Types | `require-trusted-types-for`, `trusted-types` | `'script'` ; des noms de politiques, `'none'`, `'allow-duplicates'`, `*` |

`report-uri` et `report-to` ne sont pas déclarées : le bundle les écrit à partir de la configuration des
[rapports](reports.md). Une directive qui reçoit `false` est laissée de côté, ce qui sert à en retirer une d'une
politique parente.

## Valeurs

| Valeur | Exemple |
|---|---|
| Un mot-clé entre guillemets | `'self'`, `'none'`, `'unsafe-inline'`, `'unsafe-eval'`, `'strict-dynamic'`, `'unsafe-hashes'`, `'report-sample'`, `'wasm-unsafe-eval'`, `'inline-speculation-rules'`, `'unsafe-allow-redirects'` |
| Un nonce ou une empreinte | `'nonce-…'`, `'sha256-…'`, `'sha384-…'`, `'sha512-…'` |
| Un schéma | `data:`, `blob:`, `https:` |
| Un hôte | `cdn.example.com`, `*.example.com`, `https://api.example.com:8443/v1/` |
| Tout | `*` |

Une valeur fait au plus 1 024 octets, et ne contient jamais d'espace, de `;`, de `,` ni de guillemet égaré : aucune
valeur ne peut ajouter une directive ou une politique à elle. Un mot-clé écrit sans ses guillemets (`self`, `none`,
`nonce-abc`) est refusé avec un indice, puisqu'un navigateur le lirait comme un hôte. Une directive de sources qui ne
reçoit aucune valeur vaut `'none'`, et `'none'` reste seul.

## La politique par défaut

Sans aucune politique dans la configuration, `default` vaut :

```text
default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self';
connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self';
upgrade-insecure-requests
```

Tout vient du site lui-même ; rien n'est intégré comme objet ; `<base>` et les formulaires restent sur le site ; seul le
site encadre ses pages ; les requêtes passent par HTTPS. Les nonces de chaque page sont ajoutés à `script-src` et à
`style-src`.

## Politiques nommées

Une page qui a besoin d'une autre politique la nomme. L'aperçu de CSS non fiable dans un `<iframe>` en bac à sable est le
cas typique :

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"], object-src: ["'none'"] }
        preview:
            directives:
                default-src: ["'none'"]
                style-src: ["'unsafe-inline'"]
                img-src: ['data:']
                frame-ancestors: ["'self'"]
                sandbox: true
            report: false
```

Un contrôleur ou une route la choisit ensuite, voir [Surcharges](overrides.md).

## Héritage

`extends` prend les directives d'une autre politique et remplace celles que la politique déclare :

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"], img-src: ["'self'"] }
        gallery:
            extends: default
            directives: { img-src: ["'self'", 'https://images.example.com'] }
```

`gallery` vaut `default-src 'self'; img-src 'self' https://images.example.com`. Une politique peut en étendre une qui en
étend une autre ; un cycle fait échouer la construction. `strict_dynamic`, `report`, `inline` et `trusted_types` sont
propres à la politique.

## Empreintes du code en ligne

Le code en ligne qu'une politique connaît d'avance est permis par son SHA-256, calculé à la construction du conteneur :

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"] }
            inline:
                script: ["document.documentElement.classList.add('js')"]
```

Le contenu doit être exact, espaces compris. Une empreinte peut aussi s'écrire comme source, `'sha256-…'`, et un service
permet le code en ligne écrit à l'exécution par `ContentSecurityPolicyContext::allowInline()`.

## Trusted Types

```yaml
phil_browser_security:
    policies:
        default:
            trusted_types:
                enabled: true
                policies: [app, dompurify]
                allow_duplicates: false
```

ajoute `require-trusted-types-for 'script'; trusted-types app dompurify` : les puits du DOM ne prennent alors que les
objets que ces politiques créent. Sans politiques, aucune politique ne peut être créée.
