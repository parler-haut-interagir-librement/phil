<!-- phil-dev source-sha256=23e14cbddd4283c4fb2264ffd719876fdb26ce76d81dd9bf69c2956f1c0ca770 -->
# Correspondance avec ASVS 5.0

Les exigences d'[OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) que le bundle satisfait, à côté de
celles de `phil/upload-scan`, avec le test qui montre chacune, et celles qu'il laisse à l'application. Le guide du
monodépôt,
[ASVS 5.0 : ce que l'application garde](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md),
rassemble ce que chaque paquet laisse.

## Satisfaites par le bundle

| Exigence | Niveau | Comment | Démontrée par |
|---|---|---|---|
| 5.3.1 Fichiers téléversés jamais servis depuis un dossier public | 1 | La quarantaine est refusée sous `public/` à la construction du conteneur, telle qu'écrite et une fois les liens symboliques suivis ; aucune route ne la sert | `PhilUploadScanBundleTest::testTheQuarantineIsNeverUnderPublic`, `testTheQuarantineIsNeverUnderPublicThroughASymbolicLink` |
| 5.3.2 Chemins de fichiers tirés de données générées, jamais des noms envoyés | 1 | Chaque fichier en quarantaine est nommé par 32 chiffres hexadécimaux aléatoires ; le nom envoyé n'est que stocké dans son enregistrement | `FilesystemQuarantineTest` |
| 16.4.1 Données encodées contre l'injection | 2 | Le nom envoyé est conservé sans ses caractères de contrôle et coupé à 255 octets | `FilesystemQuarantineTest`, la cible de fuzzing |
| 16.5.3 Échouer de façon sûre | 2 | Un fichier qui ne peut être analysé est refusé sous `on_error: reject` ; une quarantaine qui ne peut conserver un fichier le refuse quand même | `UploadScannerTest`, `CleanFileValidatorTest` |
| 3.5.1 Requêtes qui changent l'état vérifiées quant à leur origine | 1 | Les suppressions de la page d'administration exigent un jeton CSRF | [CSRF](csrf.md) et ses tests |

## Laissées à l'application

| Exigence | Niveau | Pourquoi elle revient à l'application |
|---|---|---|
| 5.4.1, 5.4.2 Noms de fichiers servis et encodés | 2 | Les en-têtes de la réponse qui sert un fichier : le contenu d'utilisateur de `phil/browser-security-bundle` |
| L'ordre de la validation et du vote | — | Avec `#[MapUploadedFile]` comme sujet de `#[IsGranted]`, vérifiez d'abord le compte ([validation](validation.md#vérifier-laccès-avant-lanalyse)) |
