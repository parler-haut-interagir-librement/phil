<!-- phil-dev source-sha256=5607fe4b8f5b22650a67c03607fa3b90f5891ea9e650a07a3ad71f72f7abd686 -->
# Configuration

La configuration du bundle se trouve sous `phil_moderation` :

```yaml
# config/packages/phil_moderation.yaml
phil_moderation:
    connection: default
    reasons: [spam, harassment, hate, violence, sexual, personal-information, illegal, off-topic, other]
    roles:
        - { name: moderator, attribute: ROLE_MODERATOR }
        - { name: comment-keeper, attribute: ROLE_COMMENT_KEEPER, types: [comment, reaction] }
        - { name: administrator, attribute: ROLE_ADMIN }
    admin:
        layout: '@PhilModeration/layout.html.twig'
        page_size: 25
    journal:
        page_size: 50
    sandbox:
        types: [forum.post, question, listing]
    surge:
        reports: 25
        within: 3600
    statements:
        enabled: true
        grounds:
            hate: { ground: illegal, reference: 'Criminal Code, s. 319' }
        terms_url: /terms
        complaint_url: /contact/moderation
        out_of_court:
            - { name: 'A certified dispute settlement body', url: 'https://settlement.example' }
        notification_type: ~
        page_size: 25
    kit_subdirectory: ~
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `enabled` | `true` | Enregistre les services du bundle ; `false` n'en enregistre aucun. |
| `connection` | `default` | La connexion DBAL des tables, par son nom dans la configuration de Doctrine. |
| `reasons` | les neuf ci-dessus | Les raisons qu'offre un formulaire de signalement, dans l'ordre ; des codes de lettres minuscules, de chiffres, de `-` et de `_`. Leurs libellés sont traduits sous `reason.<code>` dans le domaine `phil_moderation`. |
| `roles` | `moderator` (`ROLE_MODERATOR`), `administrator` (`ROLE_ADMIN`) | Les rôles de la modération, dans l'ordre où ils sont essayés : un membre qui en détient plusieurs agit sous le premier. `types` énumère les types de cibles sur lesquels un rôle décide ; tous les types quand la liste est vide. Le journal public montre le nom, traduit sous `role.<name>`. |
| `admin.layout` | `@PhilModeration/layout.html.twig` | Le gabarit que les pages étendent ; il définit les blocs `title` et `main`. |
| `admin.page_size` | `25` | Les lignes de la file par page, de 1 à 200. |
| `journal.page_size` | `50` | Les lignes du journal public par page, de 1 à 200. |
| `sandbox.types` | aucun | Les types de cibles dont le premier contenu d'un membre attend la modération. |
| `surge.reports`, `surge.within` | `25`, `3600` | Quand autant de signalements arrivent en autant de secondes (de 60 à 86 400), la file propose le mode d'incident de `phil/incident-mode-bundle` au rôle de son administration ([Mode d'incident](incident-mode.md)). |
| `statements.enabled` | `true` | Si une décision qui restreint l'auteur s'accompagne d'un exposé des motifs ([Exposés des motifs](statements-of-reasons.md)) ; `statements: false` les désactive. |
| `statements.grounds` | aucun | Sur quoi repose chaque raison, par son code : `ground` vaut `terms` ou `illegal`, `reference` est la disposition qu'un exposé nomme par défaut. Une raison absente de la liste repose sur les conditions ; `illegal` repose sur la loi. |
| `statements.terms_url`, `statements.complaint_url` | `null` | Les conditions du site et la page où un membre conteste une décision : un chemin du site ou une adresse `https`. |
| `statements.out_of_court` | aucun | Les organismes de règlement extrajudiciaire des différends que le site accepte, chacun avec un `name` et un `url`. |
| `statements.notification_type` | `null` | Le type de `phil/notifications-bundle` qui informe un membre qu'un exposé lui est adressé ; aucune notification quand la valeur est `null`. |
| `statements.page_size` | `25` | Les exposés par page dans la liste du membre, de 1 à 200. |
| `kit_subdirectory` | `null` | L'emplacement des recettes du kit, sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand la valeur est `null`. |
