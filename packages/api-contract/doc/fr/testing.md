<!-- phil-dev source-sha256=f3152831033d711c54c337dc4861730649d12a31a7c59626ec3d8ab8895b52e8 -->
# Tests

## Ce que couvrent les tests de la bibliothèque

La suite unitaire se trouve dans `tests/Unit` et s'exécute avec PHPUnit 13. Elle n'a besoin d'aucune base de données,
d'aucun noyau ni d'aucun réseau.

| Classe de test | Ce qu'elle fixe |
|---|---|
| `ProblemDetailsTest` | L'écriture stricte (statut d'erreur, `type` absolu, noms d'extension, aucun masquage), l'omission des membres absents, la lecture tolérante (mauvais types JSON ignorés, `type` relatif lu comme blanc, membre `status` comparé au statut de la réponse), l'aller-retour. |
| `DeprecationSignalTest` | Les trois en-têtes écrits, les parties absentes omises, un retrait qui précède l'obsolescence refusé, les liens qui briseraient l'en-tête refusés, les valeurs mal formées ignorées à la lecture (dont le `Deprecation: true` antérieur à la RFC), les paramètres de champ structuré retirés, les dates contradictoires qui gardent le retrait, l'arrondi de `noticeInDays()`, l'aller-retour avec des fractions de seconde. |
| `LinkHeaderTest` | `rel` entre guillemets ou non, l'insensibilité à la casse, plusieurs relations, plusieurs entrées et lignes, le premier `rel` seulement, un `rel` dans un paramètre entre guillemets ignoré, la cible vide. |
| `HttpDateTest` | L'IMF-fixdate en UTC à l'écriture; les trois formes de la RFC 9110, section 5.6.7, pour le même instant (`784111777`); les jours impossibles, les mauvais jours de la semaine, les autres fuseaux et le texte à la fin refusés. |
| `TolerantEnumTest` | Les valeurs par défaut et les refus pour les enums à valeur chaîne et entière, l'absence de conversion, le message borné et échappé. |
| `OpenApiDocumentTest` | Les objets JSON lus, les listes et les scalaires refusés, les bornes de taille, de profondeur et de nombre de valeurs jusqu'à la limite exacte, les pointeurs JSON, les références locales et leurs chaînes jusqu'à seize, les opérations dans l'ordre, les paramètres redéfinis par ceux de l'opération, les réponses résolues, le parcours sans exemples ni extensions. |
| `ContractVerifierTest` | Les niveaux et ce que chacun contient, les gravités, les règles intégrées par niveau, les constats dans l'ordre des règles, `fails()` et `reaching()`, les règles écartées par identifiant, les identifiants de règle refusés, les constats gardés imprimables et bornés. |
| `MinimalRulesTest`, `RecommendedRulesTest`, `StrictRulesTest` | Le niveau, la gravité et la description de chaque règle; les documents qu'elle laisse passer, ainsi que l'emplacement et le message exacts de chaque constat qu'elle produit. |

### Valeurs de référence

- L'instant d'exemple de la RFC 9110, section 5.6.7, `Sun, 06 Nov 1994 08:49:37 GMT`, dans ses trois formes,
  correspond au temps Unix `784111777`.
- Le problème d'exemple de la RFC 9457 (`out-of-credit`, solde de 30, instance `/account/12345/msgs/abc`) sert de jeu
  de données d'essai pour le document complet.

## Exécuter les tests

Depuis la racine du monodépôt :

```bash
vendor/bin/phpunit packages/api-contract/tests
```

Installée seule, dans une copie neuve de la bibliothèque :

```bash
cd api-contract
composer install
vendor/bin/phpunit --no-configuration tests
```

Infection tient la bibliothèque à 100 % de mutants tués.

## Tester vos adaptateurs

Le port est assez petit pour être testé directement contre son contrat.

**`ObservedDeprecations`** : vérifiez que le premier `record()` retourne `true`, qu'un deuxième dans la journée
retourne `false`, qu'un troisième un jour plus tard retourne `true`, et que `all()` contient le dernier signal par
point de terminaison.

**`OpenApiRule`** : vérifiez des documents construits comme des tableaux avec `OpenApiDocument::fromArray()`, et
vérifiez l'emplacement et le message exacts de chaque constat, comme le fait `MinimalRulesTest`; incluez des valeurs du
mauvais type là où la règle attend un objet ou une chaîne.

**Votre intégration HTTP** : écrivez les réponses avec `DeprecationSignal::toHeaders()` et
`ProblemDetails::toArray()`, puis relisez-les avec `fromHeaders()` et `fromArray()`. Incluez des champs inconnus, des
valeurs d'énumération inconnues et un `type` de problème inconnu dans les jeux de données d'essai de votre contrat, et
vérifiez que le client se dégrade comme le décrit [tolerant-reading.md](tolerant-reading.md).

## Fuzzing

La cible de fuzzing `openapi-verifier`, dans `tests/Fuzz`, donne à la vérification au niveau strict des entrées que
personne n'a écrites : quoi qu'elle lise, elle retourne un rapport ou refuse le document comme illisible, et ne lève
jamais autre chose ni n'émet d'avertissement. Ses germes sont dans `tests/Fuzz/corpus/openapi-verifier`. Depuis la
racine du monodépôt :

```sh
composer fuzz -- openapi-verifier
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine
du monodépôt :

```sh
composer bench -- packages/api-contract/tests/Benchmark
```
