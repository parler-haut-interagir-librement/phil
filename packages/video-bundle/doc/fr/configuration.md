<!-- phil-dev source-sha256=03a1c5e3650cf0e2399a14ff82c4f94d7731fe9b1e4253c0484d9f0b77cd6533 -->
# Configuration

```yaml
phil_video:
    tracking:
        enabled: false
        endpoint: /_phil/video/progress
        consent: null          # a ViewingConsent service; nobody agreed when null
        store: memory          # memory, cache, or a ViewingStore service
        cache_pool: cache.app  # the PSR-6 pool of the cache store
    kit_subdirectory: null
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `tracking.enabled` | `false` | Si les visionnements sont comptés ou non |
| `tracking.endpoint` | `/_phil/video/progress` | Le chemin auquel le lecteur envoie les visionnements : la route de `@PhilVideoBundle/config/routes.php` |
| `tracking.consent` | `null` | Le service, un `ViewingConsent`, qui dit si le spectateur courant a accepté — le gestionnaire de consentement de l'application. `NoViewingConsent` quand elle est nulle : personne n'a accepté, rien n'est compté |
| `tracking.store` | `memory` | `memory`, vidé entre deux requêtes, pour les tests ; `cache`, dans le pool ci-dessous ; ou le service d'un `ViewingStore` |
| `tracking.cache_pool` | `cache.app` | Le pool PSR-6 du stockage `cache` |
| `kit_subdirectory` | `null` | L'endroit des recettes du kit sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand elle est nulle |
