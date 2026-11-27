<!-- phil-dev source-sha256=f24735b8405b860d18bd123988dae2ca2c394c8bfb4aaf140fbebc308b303c08 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/rate-limit` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-17

### Ajouté

- `StoreLocality` lit une fabrique de limiteurs de débit : une `RateLimiterFactory` définie à la main, son stockage
  donné par position ou par nom, et un limiteur de la configuration du framework, `limiter.<name>`, enfant du `limiter`
  abstrait, sont aussi locaux que leur stockage. Un bundle à qui l'on donne l'identifiant d'un limiteur peut le remettre
  à `RateLimitGuard::watch()`.
- `StoreLocality::LIMITER`, le service abstrait des limiteurs du framework.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `RateLimitGuard`, la passe de compilation qui fait échouer la construction du conteneur sur une limite de débit qui ne
  limiterait pas : `requireComponent()` refuse une limite activée sans `symfony/rate-limiter`, `watch()` note chaque
  pool de cache ou stockage par le chemin de configuration qui le nomme, avec `single_server` et l'option qui désactive
  la limite, et la passe refuse dans l'environnement `prod` un stockage local à chaque serveur ; chaque bundle partage
  un paramètre caché, vérifié une fois.
- `StoreLocality` : un pool sur l'adaptateur de système de fichiers, APCu, de tableau ou système de `symfony/cache`, par
  parent ou par classe, l'`InMemoryStorage` de `symfony/rate-limiter` et un `CacheStorage` à qui l'on donne un tel pool
  sont locaux ; les alias et les parents sont suivis, 32 services au plus, et une boucle est laissée au conteneur.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README, et son miroir français.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
