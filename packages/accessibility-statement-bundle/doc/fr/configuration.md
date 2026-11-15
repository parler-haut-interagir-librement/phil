<!-- phil-dev source-sha256=78832585780d38e903374137223251a3560c9c31ca925993052ba72d57cb9cc8 -->
# Configuration

```yaml
phil_accessibility_statement:
    source: config              # or doctrine
    connection: default         # the DBAL connection of the doctrine source
    clock: clock                # the PSR-20 clock that dates a regenerated version
    default_locale: en          # the language a text falls back to
    wording: wcag               # or en-301-549, aca
    layout: '@PhilAccessibilityStatement/layout.html.twig'
    cache_max_age: 3600         # seconds; 0 makes caches ask each time
    feedback_route: ~           # such as phil_contact_form
    feedback_form: ~            # the key of a form of phil/forms-bundle
    kit_subdirectory: ~         # the subdirectory of the recipes; the Toolkit's when null
    statement: ~                # the statement, as StatementReader reads it
    audit:
        enabled: false
        source: ~               # the service id of an AuditSource of the application's own
        directory: '%kernel.project_dir%/var/accessibility'
        minimum_severity: error # or warning, notice
        method: automated       # or self-assessment, third-party
        report_url: ~
    admin:
        enabled: false
        role: ROLE_ADMIN        # the role Symfony Security grants to open the page
```

| Clé | Signification |
|---|---|
| `source` | `config` : la déclaration ci-dessous est la seule version, en lecture seule. `doctrine` : les versions sont gardées dans la table `phil_accessibility_statement` ([Doctrine](doctrine.md)) ; la déclaration ci-dessous est une version tant que la table n'en contient aucune de ce numéro. |
| `connection` | La connexion DBAL de la table. |
| `clock` | L'identifiant du service d'horloge qui date une version régénérée. |
| `default_locale` | La langue dont la page montre le texte quand un texte n'existe pas dans la langue de la requête. |
| `wording` | Le libellé de la page ([La page](page.md#le-libellé)). |
| `layout` | La mise en page Twig que la page étend ; elle définit les blocs `title` et `content`. |
| `cache_max_age` | Combien de temps les navigateurs et les caches partagés peuvent garder la page : `Cache-Control: public, max-age=…`, ou `no-cache` pour 0. |
| `feedback_route` | La route d'une page qui reçoit la rétroaction, liée en premier parmi les moyens : le formulaire de contact de `phil/contact-form-bundle`, `phil_contact_form`, ou une page de l'application. |
| `feedback_form` | La clé d'un formulaire de `phil/forms-bundle`, de 1 à 64 lettres minuscules, chiffres ou `-` : la page lie vers sa page, la route `phil_forms_form`, avant `feedback_route`. |
| `kit_subdirectory` | Où se trouvent les recettes du kit, sous le répertoire des composants : `ui` pour `ui:Card`. Null lit le sous-répertoire de la configuration `ux_toolkit` ; réglez-le là où le Toolkit n'est pas activé. |
| `admin` | La page d'administration ([Administration](administration.md)) : `enabled`, et le `role` qui l'ouvre. |
| `statement` | La déclaration, avec les clés que lit `StatementReader` de `phil/accessibility-statement` (sa documentation les énumère, sous « Plain data »). Avec la source `config` et sans déclaration, aucune n'est encore publiée : la page répond 404 et le pied de page ne la lie pas. |
| `audit` | Le dernier audit que lit la commande ([Console](console.md)) : un répertoire de rapports, ou un service `AuditSource`. |

La déclaration est lue à la construction du conteneur. Une déclaration qui ne peut pas être publiée fait échouer la
construction avec le motif, comme `phil_accessibility_statement.statement: A statement that lists no criterion not met
claims full conformance.`

YAML lit une date sans guillemets comme `2026-11-08` comme un horodatage. Le bundle réécrit alors `published_at`,
`evaluation.evaluated_at` et le `fix_by` de chaque non-conformité comme le moment qu'ils représentent, à minuit UTC.
Mettez-les entre guillemets pour garder la date telle qu'elle est écrite.

Le conteneur lit `%name%` dans une chaîne de la configuration comme un paramètre. Un signe de pourcentage dans un texte
s'écrit donc `%%`, comme dans `Captions cover 80%% of the videos.`

## Une déclaration complète

```yaml
phil_accessibility_statement:
    wording: en-301-549
    feedback_route: phil_contact_form
    statement:
        version: 3
        published_at: '2026-11-08'
        standard: wcag-2.1
        level: AA
        entity: Example Org
        scope: { en: the site example.com and its booking application }
        feedback:
            email: accessibility@example.com
            phone: '+1 514 555 0100'
            postal_address: { en: "1 Main Street\nMontréal QC" }
            response_days: 10
        non_conformities:
            - criterion: '1.2.2'
              description: { en: Videos published before 2024 have no captions. }
              alternative: { en: A transcript is sent on request. }
              fix_by: '2027-06-30'
            - criterion: '2.1.1'
              reason: out-of-scope
              description: { en: The map of a partner cannot be used with a keyboard. }
        alternatives:
            - { en: 'Call us: we book for you by telephone.' }
        enforcement:
            procedure: { en: If our answer does not satisfy you, you may refer the matter to the authority. }
            authority: { en: The national accessibility authority }
            url: https://example.org/complaints
        evaluation: { evaluated_at: '2026-10-30', method: third-party, pages: 40, report_url: /accessibility/report.pdf }
```

## Les services

| Service | Quand |
|---|---|
| `StatementRepository` (alias, public) | Toujours : `ConfiguredStatements` ou `DbalStatements`. |
| `StatementController` | Toujours : la page. |
| `StatementExtension`, `KitComponents` | Toujours : les fonctions et le filtre Twig, les noms des recettes. |
| `StatementSchemaListener` | Avec la source `doctrine`. |
| `AuditSource` (alias) | Avec `audit` : `ReportDirectory`, ou le service nommé. |
| `StatementRegenerator` (public) | Avec `audit`. |
| `RegenerateCommand` | Avec `audit`, quand `symfony/console` est installé. |
| `StatementPublisher` (public), `StatementAdminController` | Avec `admin`. |
