<!-- phil-dev source-sha256=ab0c1346d53f9406d9261d5a093cae944563f092a7e848f1737e20a906d74f43 -->
# Déploiement

Une politique qui bloque ce dont le site a besoin le brise. Le chemin sûr fait d'abord tourner la politique en
report-only, lit les rapports, et l'applique quand ils se sont tus.

## D'abord en report-only

```yaml
phil_browser_security:
    enforce: ~
    report_only: default
```

Les navigateurs n'appliquent rien et signalent chaque violation au [point d'entrée](reports.md). Les rapports disent de
quelle source chaque page a besoin.

## Appliquée, avec une politique plus stricte à côté

```yaml
phil_browser_security:
    enforce: default
    report_only: strict
    policies:
        default:
            directives: { default-src: ["'self'"], img-src: ["'self'", 'https:'] }
        strict:
            extends: default
            directives: { img-src: ["'self'", 'https://images.example.com'] }
            strict_dynamic: true
```

La politique actuelle continue de protéger pendant qu'on essaie la plus stricte sur du vrai trafic. Chaque en-tête porte
ses propres directives de rapport, et les rapports disent de quelle politique ils viennent par leur disposition :
`enforce` ou `report`.

## Une route à la fois

Une route ou un contrôleur envoie sa politique en report-only pendant que les autres l'appliquent :

```php
#[ContentSecurityPolicy(reportOnly: true)]
public function checkout(): Response
```

Dans ce cas, la politique report-only de la configuration n'est pas envoyée sur cette réponse : un seul en-tête
`Content-Security-Policy-Report-Only` à la fois.
