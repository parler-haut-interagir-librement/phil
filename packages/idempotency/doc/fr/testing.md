<!-- phil-dev source-sha256=9ca3db3ef2a1352056f32ce657c32f7b50fe366f194929ef8e6ef8ba06612122 -->
# Tests

```bash
vendor/bin/phpunit packages/idempotency/tests
```

- **Unit** couvre chaque valeur, la table de décision, la garde contre un stockage qui note ses appels, l'intergiciel
  Messenger, et le contrat de `IdempotencyStore` exécuté contre les deux adaptateurs : SQLite en mémoire pour la
  table, un pool en tableau et un stockage de verrous en mémoire pour le cache. Le matériau de l'empreinte est fixé.
- **Integration** démarre huit processus PHP qui demandent la même clé au même moment, une fois par un fichier SQLite
  et une fois par un pool sur système de fichiers sous verrous de fichiers : exactement un s'exécute, et les sept
  autres apprennent qu'une exécution est en cours.
- **Fuzz** : la cible `idempotency-key` donne au lecteur de l'en-tête du texte quelconque ; tout ce qu'il lit doit se
  relire en la même clé depuis sa forme entre guillemets, et il ne lève que les exceptions de la bibliothèque.

```bash
composer fuzz -- idempotency-key --budget=60
```

## Dans vos tests

Donnez à la garde une `Symfony\Component\Clock\MockClock` et avancez-la pour franchir une expiration. Le stockage DBAL
fonctionne sur SQLite en mémoire une fois sa table créée par `IdempotencyTable::configure()` ; le stockage en cache sur
un `ArrayAdapter` et un `InMemoryStore` de verrous.
