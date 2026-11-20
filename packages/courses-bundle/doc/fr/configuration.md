<!-- phil-dev source-sha256=dc65b491a1fafcb24537ef09f31b314d6077863532f4686f0d851d6514fb27da -->
# Configuration

La configuration du bundle se trouve sous `phil_courses`. Chaque option a une valeur par défaut ; une application nomme
l'origine du contenu et la page d'accueil des comptes cmi5.

```yaml
# config/packages/phil_courses.yaml
phil_courses:
    connection: default
    timezone: America/Toronto
    current_learner: null
    layout: '@PhilCourses/layout.html.twig'
    kit_subdirectory: null
    self_enrollment: false
    locked_activities: show
    storage:
        directory: '%kernel.project_dir%/var/phil_courses/content'
        service: null
    content:
        base_url: 'https://content.example.org'
        application_origin: null
        allow_localhost: false
    import:
        language: null
        xapi_reader: null
        limits:
            archive_bytes: 536870912
            entries: 10000
            entry_bytes: 268435456
            total_bytes: 2147483648
            ratio: 200
            manifest_bytes: 4194304
            depth: 16
            items: 5000
            manifest_nodes: 500000
            manifest_depth: 64
    cmi5:
        actor_home_page: 'https://www.example.org'
        session_ttl: 14400
        secret: '%kernel.secret%'
        allowed_origins: []
        lrs: null
        remote_lrs:
            enabled: false
            endpoint: null
            username: null
            password: null
            version: 1.0.3
            timeout: 5.0
            max_duration: 10.0
    scorm:
        enabled: true
        token_ttl: 14400
        secret: '%kernel.secret%'
    training:
        enabled: true
        reminder_days: 7
        renewal_grace_days: 30
        max_members: 10000
        run_at: '06:00'
        schedule: true
        manager_role: ROLE_COURSES_MANAGER
        notifier: null
        keep_transcripts: false
    admin:
        role: ROLE_COURSES_TEACHER
    audit:
        enabled: true
        segment: courses
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `connection` | `default` | La connexion DBAL des tables. |
| `timezone` | `UTC` | Le fuseau horaire du site : une ouverture un nombre de jours après l'inscription a lieu à minuit dans ce fuseau. |
| `current_learner` | `null` | Le service `CurrentLearner` ; l'identifiant d'utilisateur de Symfony Security si null, refusé s'il contient un `@`. |
| `layout` | `@PhilCourses/layout.html.twig` | Le gabarit que les pages étendent, avec les blocs `title` et `main`. |
| `kit_subdirectory` | `null` | L'emplacement des recettes du kit sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` si null. |
| `self_enrollment` | `false` | Si un apprenant connecté s'inscrit depuis la page du cours ; si false, l'application l'inscrit. |
| `locked_activities` | `show` | `show` énumère les activités qu'un apprenant ne peut pas encore suivre avec ce qui reste à faire ; `hide` les omet du plan. |
| `storage.directory` | `%kernel.project_dir%/var/phil_courses/content` | Le répertoire des fichiers importés, hors du répertoire public. |
| `storage.service` | `null` | Un service `ContentStorage` de l'application, au lieu du répertoire. |
| `content.base_url` | `null` | L'URL de base de l'origine du contenu ; si null, le contenu importé n'a aucun endroit d'où se lancer. |
| `content.application_origin` | `null` | L'origine du site qui encadre le contenu, comme `https://www.example.org` : la seule origine qui peut encadrer le contenu et le cadre de l'environnement d'exécution SCORM, et la seule à laquelle le cadre parle. L'origine de `cmi5.actor_home_page` quand elle est null ; avec ni l'une ni l'autre, personne ne peut encadrer le contenu. |
| `content.allow_localhost` | `false` | Si l'origine du contenu et la page d'accueil peuvent être sur `localhost`, en développement. |
| `import.language` | `null` | La langue des titres lus dans une structure de cours cmi5, comme `fr` ; son premier titre si null. |
| `import.xapi_reader` | `null` | Un service `XapiPackageReader` qui lit le `tincan.xml` des paquets xAPI ; `ElearningXapiPackageReader`, sur le lecteur de phil/elearning-packages, quand il vaut null. Voir [activités xAPI](xapi-activities.md#importer-un-paquet-xapi). |
| `import.limits.archive_bytes` | 512 Mio | La taille de l'archive. |
| `import.limits.entries` | 10 000 | Le nombre d'entrées de l'archive. |
| `import.limits.entry_bytes` | 256 Mio | La taille d'un fichier, décompressé. |
| `import.limits.total_bytes` | 2 Gio | La taille de tous les fichiers, décompressés. |
| `import.limits.ratio` | 200 | Combien de fois un fichier de plus d'un mégaoctet peut être compressé. |
| `import.limits.manifest_bytes` | 4 Mio | La taille de `imsmanifest.xml` ou de `cmi5.xml`. |
| `import.limits.depth` | 16 | La profondeur d'imbrication des éléments ou des blocs, 64 au plus. |
| `import.limits.items` | 5 000 | Le nombre d'éléments, de ressources, de blocs ou d'unités que décrit un manifeste. |
| `import.limits.manifest_nodes` | 500 000 | Le nombre de nœuds XML d'un manifeste, comptés avant son chargement. |
| `import.limits.manifest_depth` | 64 | La profondeur d'imbrication des éléments XML d'un manifeste, vérifiée avant son chargement. |
| `cmi5.actor_home_page` | `null` | L'adresse du site : la page d'accueil des comptes qui nomment les apprenants, et la racine des IRI des activités. Si null, les lancements cmi5 sont refusés. |
| `cmi5.session_ttl` | 14 400 | Pendant combien de secondes le jeton d'une session est valide, de 60 à 86 400. |
| `cmi5.secret` | `%kernel.secret%` | Le secret dont les jetons sont dérivés. |
| `cmi5.allowed_origins` | `[]` | Les origines, en plus de l'origine du contenu, dont les unités peuvent appeler le point de terminaison xAPI : celles des unités à URL absolue. |
| `cmi5.lrs` | `null` | Un service `LearningRecordStore` de l'application qui conserve les énoncés ; l'entrepôt intégré au bundle, ou l'entrepôt distant, si null. Refusé quand `remote_lrs` est activé. |
| `cmi5.remote_lrs.enabled` | `false` | Envoie les énoncés à un entrepôt de traces d'apprentissage xAPI distant par phil/safe-http, qui doit être installé ; activé dès qu'une autre clé de `remote_lrs` est donnée. |
| `cmi5.remote_lrs.endpoint` | `null` | Le point de terminaison xAPI de l'entrepôt, une adresse `https` d'un hôte public sans requête, comme `https://lrs.example.org/xapi/` ; obligatoire quand il est activé. |
| `cmi5.remote_lrs.username` | `null` | Le nom d'utilisateur de l'authentification HTTP Basic — la clé que donne l'entrepôt —, sans `:` ; aucun si null. |
| `cmi5.remote_lrs.password` | `null` | Le mot de passe de l'authentification HTTP Basic — le secret que donne l'entrepôt —, lu dans une variable d'environnement ; donné avec le nom d'utilisateur, ou pas du tout. |
| `cmi5.remote_lrs.version` | `1.0.3` | La version de xAPI que parle l'entrepôt, `1.0.3` ou `2.0.0`, envoyée dans `X-Experience-API-Version`. |
| `cmi5.remote_lrs.timeout` | 5.0 | Pendant combien de secondes l'entrepôt peut rester silencieux, de 0,1 à 60. |
| `cmi5.remote_lrs.max_duration` | 10.0 | Combien de secondes l'envoi d'un énoncé peut prendre, en tout, de 0,1 à 120. |
| `scorm.enabled` | `true` | Exécute le contenu SCORM avec l'API d'exécution, ses tentatives et ses données de suivi, comme l'explique [Environnement d'exécution SCORM](scorm-runtime.md) ; quand c'est false, l'apprenant marque une activité SCORM terminée. |
| `scorm.token_ttl` | 14 400 | Combien de secondes un jeton de tentative tient après la dernière validation, de 300 à 86 400. |
| `scorm.secret` | `%kernel.secret%` | Le secret qui signe les jetons de tentative. |
| `training.enabled` | `true` | La formation obligatoire — assignations, rappels, recertifications, crédits et relevés —, ses pages, ses commandes, son calendrier et son module de vie privée. |
| `training.reminder_days` | 7 | Combien de jours avant l'échéance un cours est bientôt dû et son premier rappel envoyé, de 1 à 90. |
| `training.renewal_grace_days` | 30 | Combien de jours après son renouvellement une recertification est de nouveau due, de 1 à 365. |
| `training.max_members` | 10 000 | Combien de membres d'un groupe ou d'une unité une assignation joint. |
| `training.run_at` | `06:00` | L'heure de l'exécution quotidienne du calendrier, `HH:MM` dans le fuseau horaire du site. |
| `training.schedule` | `true` | Si le calendrier `phil_courses_training` s'exécute chaque jour, avec Symfony Scheduler et Messenger installés. |
| `training.manager_role` | `ROLE_COURSES_MANAGER` | Le rôle qu'un gestionnaire détient pour voir la formation de chaque apprenant et leurs relevés. |
| `training.notifier` | `null` | Un service `TrainingNotifier` ; les notifications de phil/notifications-bundle quand il vaut null et que ce bundle est enregistré, aucune sinon. |
| `training.keep_transcripts` | `false` | Si les relevés survivent à une demande d'effacement, en vertu d'une obligation légale de les garder. |
| `admin.role` | `ROLE_COURSES_TEACHER` | Le rôle qu'un enseignant détient pour voir et trancher les secondes chances sur la page d'administration. |
| `audit.enabled` | `true` | Écrit les secondes chances — demandées, accordées, refusées, dispensées — dans la piste de phil/audit-bundle, quand ce bundle est enregistré. |
| `audit.segment` | `courses` | Le segment de la piste. |

Le bundle déclare le jeton CSRF `phil_courses` sans état à FrameworkBundle : les formulaires des pages et les validations
des sessions SCORM fonctionnent sans session. Avec phil/notifications-bundle enregistré, il déclare les types de
notification de la formation obligatoire, `courses.training.*`, qu'une application modifie en déclarant un type de même
clé.
