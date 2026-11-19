<!-- phil-dev source-sha256=27d8cb56dea113c7c9d1eff705f238a1a2faf998c083cb3fcd5f4bb8b129b3e3 -->
# Tests

La suite Unit du bundle s'exécute sans noyau : le catalogue, les textes, les déclarations et leur association, la
vérification de conformité, la garde d'exportation et son événement, la page, les fonctions Twig sur un véritable
environnement Twig avec les traductions du bundle, le document UJO relu par `phil/ujo`, le JSON-LD, le voter, le dépôt
Doctrine sur une base SQLite en mémoire, la configuration du conteneur, l'impression stricte, le formulaire d'attribution
et l'assistant de licence, avec son arbre de décision pour chaque combinaison et son flux de formulaire étape
par étape. La suite Functional démarre une application avec FrameworkBundle et ses formulaires, DoctrineBundle,
TwigBundle et SecurityBundle, et une autre qui imprime avec `phil/paged-media-bundle` sous l'impression stricte. Chaque mutant du code de production est tué.

```sh
composer test
composer infection:changed
```

## Tester une application

Un `LicenseDeclarationRepository` en mémoire, soit un tableau de déclarations par sujet, tient lieu de table dans les
tests unitaires. `ExportGuard` et `LicenseCompliance` sont des objets simples construits avec un `LicenseCatalog`. Dans
un test avec noyau, créez la table avec le `SchemaTool` de Doctrine, puis vérifiez la garde d'exportation et la page par
le conteneur.
