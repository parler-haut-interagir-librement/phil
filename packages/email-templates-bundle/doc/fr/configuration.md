<!-- phil-dev source-sha256=16f066233774a6827f37f1e33ce4e295e59edb431124638b8d4c04917035f8bd -->
# Configuration

La configuration du bundle se trouve sous `phil_email_templates` :

```yaml
# config/packages/phil_email_templates.yaml
phil_email_templates:
    default_locale: en
    site: main                       # the site this application is, when phil/tenancy-bundle does not say it
    defaults_paths: ['%kernel.project_dir%/email_templates']
    from: 'Shop <shop@example.com>'
    brand: { name: 'Shop', logo: 'https://example.com/logo.png' }
    theme:
        light: { primary: '#0f766e' }
        dark: { primary: '#5eead4' }
        font: "Georgia, 'Times New Roman', serif"
    embeds: { directory: '%kernel.project_dir%/email_templates/images', max_bytes: 1048576 }
    sandbox: { filters: [], functions: [], cache: true }
    admin: { enabled: true, role: ROLE_EDITOR, layout: 'admin/email_layout.html.twig' }
    messenger: { delivery_resolver: App\Mail\Deliveries }
```

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `enabled` | `true` | Enregistre les services du bundle ; `false` n'en enregistre aucun. |
| `store` | `content` | Où les gabarits sont conservés : `content`, comme des contenus de `phil/content-bundle`, qui exige DoctrineBundle et PhilContentBundle ; `memory` pour les tests ; ou l'identifiant d'un service `TemplateStore`. |
| `entity_manager` | `doctrine.orm.entity_manager` | Le service du gestionnaire d'entités des contenus : celui que nomme `phil_content.entity_manager`. |
| `clock` | `clock` | L'horloge PSR-20 qui date les enregistrements des brouillons et les publications du magasin `memory` ; `phil/content-bundle` date ses publications avec l'horloge `clock` du conteneur. |
| `default_locale` | `en` | La locale produite quand aucune n'est donnée, et la dernière essayée. Avec le magasin `content`, chaque locale dans laquelle un site écrit des gabarits est une locale que sert `phil_content.locales`. |
| `site` | `null` | Le site qu'est l'application, quand la multilocation ne le dit pas ; `null` pour la portée partagée. |
| `site_scope` | `auto` | `tenancy`, le locataire actif de `phil/tenancy-bundle` ; `fixed`, l'option `site` ; `auto`, par locataire quand PhilTenancyBundle est activé et que `site` n'est pas défini. |
| `defaults_paths` | `['%kernel.project_dir%/email_templates']` | Les répertoires des fichiers par défaut de l'application, cherchés avant ceux du bundle. |
| `layout` | `@PhilEmailTemplates/email/layout.html.twig` | Le gabarit de la mise en page de la maison, dans le Twig de l'application. |
| `brand.name`, `brand.logo` | `null` | Ce que la mise en page par défaut montre en haut : un nom, ou l'adresse `https` d'un logo de 40 pixels de haut. |
| `theme.light`, `theme.dark` | les couleurs du bundle | Les couleurs qui remplacent celles de chaque thème : `primary`, `primary_text`, `text`, `muted`, `background`, `surface`, `panel`, `border`, chacune en `#rgb` ou `#rrggbb`. |
| `theme.font` | une pile de polices système | La police de la mise en page et des composants : lettres, chiffres, espaces, traits d'union, virgules et guillemets. |
| `embeds.directory` | `null` | Le répertoire des images que les gabarits intègrent ; sans lui, un gabarit qui en intègre une ne produit pas de rendu. |
| `embeds.max_bytes` | `1048576` | La plus grande image, en octets. |
| `from` | `null` | L'expéditeur d'un courriel composé sans expéditeur : `shop@example.com` ou `Shop <shop@example.com>`. |
| `max_html_bytes` | `1048576` | Le plus grand courriel produit, en octets de HTML ; un corps plus grand est refusé lui aussi. |
| `sandbox.filters`, `sandbox.functions` | `[]` | Les filtres et les fonctions des extensions Twig étiquetées `phil_email_templates.sandbox_extension` que le bac à sable autorise. |
| `sandbox.cache` | `true` | Si les gabarits compilés sont mis en cache sous `%kernel.cache_dir%/phil_email_templates/twig`. |
| `inline_css` | `auto` | Si les styles sont mis en ligne : `auto` quand `twig/cssinliner-extra` est installé, `true` ou `false`. |
| `admin.enabled` | `false` | Enregistre l'administration et son aperçu. |
| `admin.role` | `ROLE_ADMIN` | Le rôle que demande chaque page de l'administration. |
| `admin.layout` | `@PhilEmailTemplates/admin/layout.html.twig` | Le gabarit que les pages étendent, avec les blocs `title` et `content`. |
| `audit.enabled`, `audit.segment` | `true`, `email_templates` | Si chaque changement est consigné dans le journal de `phil/audit-bundle`, quand PhilAuditBundle est activé, et dans quel segment. |
| `messenger.delivery_resolver` | `null` | Le service `DeliveryResolver` qui traite `SendTemplatedEmail` ; pas de *handler* sans lui. |
| `preview` | `null` | Un service `PreviewRenderer` qui remplace l'aperçu du bundle. |
| `kit_subdirectory` | `null` | L'emplacement des recettes du kit, sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand la valeur est `null`. |

Le bundle ajoute son jeton CSRF, `phil_email_templates_admin`, à `framework.csrf_protection.stateless_token_ids`.

## Dans les tests

```yaml
# config/packages/test/phil_email_templates.yaml
phil_email_templates:
    store: memory
    sandbox: { cache: false }
```
