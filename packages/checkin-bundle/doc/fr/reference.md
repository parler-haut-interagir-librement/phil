<!-- phil-dev source-sha256=84b894999f50f696d56c37bd841d43b7d29f7a24ea6e18be060d5ab38b36078e -->
# Référence

| Classe | Rôle |
|---|---|
| `PhilCheckinBundle` | Le bundle : la configuration, le jeton CSRF et les ressources ajoutés en amont de la configuration, `RESOLVER_TAG`, `CATALOG_TAG`, `SCAN`. |
| `Application\Contract\AdmissionRules` | La règle de chaque point de contrôle, tirée de la configuration ou de l'application ; `ruleFor()`. |
| `Application\Contract\CredentialAttributes` | Ce que les règles lisent d'un titre d'accès comme `attributes` ; `of()`. |
| `Application\Event\CheckInRecorded` | Une lecture que les points de terminaison ont enregistrée, en ligne ou synchronisée : identifiants, sens, issue, `offline`, `conflict`. |
| `Infrastructure\DependencyInjection\CheckinServices` | Enregistre les services de la configuration. |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | Arrête un conteneur sans protection CSRF. |
| `Infrastructure\DependencyInjection\CredentialCatalogPass` | Désactive les services hors ligne sans catalogue nommé ni étiqueté. |
| `Infrastructure\Catalog\CredentialCatalogs` | Les catalogues étiquetés, interrogés ensemble dans leur ordre. |
| `Infrastructure\Doctrine\DoctrineCheckInLedger` | Le registre dans `phil_checkin_scan` ; `configureSchema()`. |
| `Infrastructure\Doctrine\CheckInSchemaListener` | Ajoute la table au schéma que génère l'ORM. |
| `Infrastructure\Http\CheckinController` | Les points de terminaison de lecture, de manifeste et de synchronisation. |
| `Infrastructure\Http\CheckinRequest` | Si une requête vient du contrôleur du poste ; `cookieName()`. |
| `Infrastructure\Http\DeskConfig` | La configuration du contrôleur du poste pour un point de contrôle ; `for()`. |
| `Infrastructure\Http\Routes` | Les noms des routes. |
| `Infrastructure\Rules\CheckpointRules` | Évalue la règle d'un point de contrôle avec ExpressionLanguage ; `has()`, `admits()`, `lint()`. |
| `Infrastructure\Rules\CheckpointDesks` | Le poste et l'émetteur de manifestes d'un point de contrôle, avec sa règle ; `desk()`, `manifests()`. |
| `Infrastructure\Rules\RuledCredentials` | Le résolveur et le catalogue tels que la règle d'un point de contrôle les voit. |
| `Infrastructure\Rules\ConfiguredAdmissionRules` | Les règles de `rules.checkpoints`. |
| `Infrastructure\Rules\NoCredentialAttributes` | Aucun attribut, sans `rules.attributes`. |

| Service | Classe |
|---|---|
| `Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks` | Le poste et les manifestes de chaque point de contrôle, sur les résolveurs étiquetés, avec sa règle. |
| `Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules` | L'évaluateur des règles. |
| `Phil\CheckinBundle\Application\Contract\AdmissionRules` | L'alias de `ConfiguredAdmissionRules`, ou de `rules.provider`. |
| `Phil\CheckinBundle\Application\Contract\CredentialAttributes` | L'alias de `NoCredentialAttributes`, ou de `rules.attributes`. |
| `Phil\Checkin\CredentialResolvers` | Les résolveurs étiquetés, interrogés ensemble. |
| `Phil\CheckinBundle\Infrastructure\Catalog\CredentialCatalogs` | Les catalogues étiquetés, interrogés ensemble, quand `catalog` est nul et qu'un est étiqueté. |
| `Phil\Checkin\AdmissionPolicy` | La politique de `policy`. |
| `Phil\Checkin\Contract\CheckInLedger` | L'alias du registre de `store`. |
| `Phil\Checkin\Offline\OfflineSync` | Avec un catalogue, nommé ou étiqueté, seulement. |
| `Phil\CheckinBundle\Infrastructure\Http\DeskConfig` | Pour les pages du poste. |

| Script | Rôle |
|---|---|
| `assets/src/checkin_desk_controller.js` | Le contrôleur Stimulus du poste. |
| `assets/src/lib/store.js` | Le magasin de l'appareil : IndexedDB, `localStorage` ou la mémoire ; ce que `localStorage` gardait est passé dans IndexedDB. |
| `assets/src/lib/offline.js` | Le manifeste, ses empreintes et le journal. |
| `assets/src/lib/decide.js` | La politique d'admission, pour un appareil hors ligne. |
| `assets/src/lib/csrf.js` | Le jeton CSRF à double soumission. |
| `assets/src/lib/i18n.js` | Les libellés en anglais et en français. |
