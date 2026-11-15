<!-- phil-dev source-sha256=7de90c6f9520e86d822139b125b237bd9e9be46fde99cae32d96eb4384efc34f -->
# Configuration

```yaml
phil_legal_notices:
    clock: clock                         # service id of the PSR-20 clock
    default_locale: en                   # the body shown when the locale of the request has none
    identity:                            # the fields of the site, by name
        site_name: Example
        publisher_name: Example Inc.
        publisher_form: Corporation
        publisher_address: "1 Main Street\nMontréal QC"
        publisher_registration: '1234567890'
        publication_director: Jo Smith
        contact_email: hello@example.com
        contact_phone: '+1 514 555 0100'
        host_name: Example Hosting
        host_address: 2 Server Road, Toronto ON
        host_contact: support@hosting.example
        dpo_name: Jo Smith
        dpo_contact: privacy@example.com
    source: null                         # service id of a DocumentSource, instead of the documents below
    documents:
        legal_notice:
            - number: 1
              format: markdown           # text by default
              state: published           # published by default; draft or withdrawn
              published_at: '2026-12-01'
              effective_at: null         # the publication date by default
              bodies: { en: '…' }
              files: { fr: '%kernel.project_dir%/legal/notice.fr.md' }
              summaries: { en: 'The host changed.' }
        privacy_policy: []
    retention:
        source: null                     # service id of a RetentionScheduleSource of phil/privacy-bundle
        personal_only: true              # only the rules that hold personal information
        labels: { R-001: { en: Your account, fr: Votre compte } }
    consent:
        source: null                     # service id of a ConsentCategories, instead of the categories below
        categories:
            necessary: { necessary: true, labels: { en: Necessary, fr: Nécessaires }, cookies: [PHPSESSID] }
            analytics:
                labels: { en: Audience measurement, fr: Mesure d'audience }
                descriptions: { en: Pages seen, without a profile. }
                cookies: ['_pk_id.*']
    markup: null                         # service id of the phil/markup Markup; one is built when it is installed
    pages:
        layout: '@PhilLegalNotices/layout.html.twig'
        retention_section: true          # the retention periods under the privacy policy
        consent_section: true            # the consent categories under the privacy policy
        kit_subdirectory: null           # where the recipes of the kit are; the subdirectory of ux_toolkit when null
    footer:
        terms_policies: []               # policies of phil/terms-bundle linked after the documents
    sitemap_section: default             # the section of phil/sitemap-bundle
```

## Les versions

Une version a un numéro, un format, un état, une date de publication sauf si c'est un brouillon, et une date d'entrée
en vigueur, qui est la date de publication si elle est omise. Une date s'écrit `2026-12-01` ou `2026-12-01T09:00`,
éventuellement avec les secondes et un décalage ; sans décalage, elle est lue en UTC. Chaque corps est donné en ligne
sous `bodies` ou comme fichier sous `files`, jamais les deux pour une même locale. Un fichier est lu à la construction
du conteneur, 256 Kio au plus, et sa modification reconstruit le conteneur en mode débogage. Les règles d'une version
sont celles de la référence de phil/legal-notices.

Les valeurs de la configuration sont lues par le conteneur, qui prend `%name%` pour un paramètre : les espaces réservés
d'un corps s'écrivent donc `${name}`, que le conteneur ne touche pas.

## Les vérifications de la construction du conteneur

La construction du conteneur échoue, en nommant l'option, sur :

- un champ de l'identité inconnu ou refusé ;
- un document autre que `legal_notice` et `privacy_policy`, une version qui enfreint une règle, une date d'une autre
  forme ;
- un corps donné deux fois pour une locale, un fichier absent, illisible ou plus gros que 256 Kio ;
- un document avec une version publiée ou retirée dont l'identité n'a pas un champ qu'il exige ;
- une catégorie de consentement qui enfreint une règle ;
- `source` en même temps que `documents`, et `retention.source` sans phil/privacy-bundle.

Une `source` de l'application n'est pas lue à la construction : `phil:legal-notices:check` signale ce qui manque à ses
documents.
