<!-- phil-dev source-sha256=0bd8f0bb17e3481c27549e6ced6997dd78b9af5b437f379062db56d4e7f06fbf -->
# Configuration

La configuration du bundle se trouve sous `phil_email_address`. Chaque option a une valeur par défaut ; l'exemple en
définit la plupart :

```yaml
# config/packages/phil_email_address.yaml
phil_email_address:
    # The bundle's policy.
    check_mail_server: false
    allow_disposable: false
    known_top_level_domain: true
    international_local_part: false
    denied_domains: [spam.example]
    allowed_domains: []
    disposable_domains: [throwaway.example]
    role_local_parts: ~
    fingerprint_key: '%kernel.secret%'

    # The policy of each site, then of each list, laid over the one above.
    current_site: App\Site\CurrentSite
    sites:
        shop: { check_mail_server: true }
    lists:
        staff: { allowed_domains: [example.org] }
        newsletter: { denied_domains: [competitor.example] }

    dns:
        resolver: ~
        cache: cache.app
        ttl: 3600

    suggestions:
        domains: ~
        url: /email-address/domains.json

    bounces:
        enabled: true
        soft_bounce_threshold: 3
        soft_bounce_window: P30D
        history: cache.app
        authserv_ids: [mx.example.net]
        feedback_domains: [fbl.example.com]
        authenticator: ~
        verp:
            enabled: true
            local_part: bounces
            domain: example.net
            separator: '+'
            key: '%env(BOUNCE_VERP_KEY)%'
        remote_event_consumers: [mailer_provider]
        recipient_metadata: recipient
        message_bus: messenger.default_bus
        mailbox: ~
        maildir: '%kernel.project_dir%/var/mail/bounces'
```

## La politique

| Option | Valeur par défaut | Effet |
|---|---|---|
| `check_mail_server` | `false` | Recherche un serveur de courriel du domaine par le résolveur configuré ; un domaine dont la recherche échoue n'est pas refusé. |
| `allow_disposable` | `false` | Accepte les domaines qui donnent des boîtes jetables. |
| `known_top_level_domain` | `true` | Refuse un domaine dont la zone racine ne délègue pas le domaine de premier niveau. |
| `international_local_part` | `false` | Accepte les caractères internationaux avant l'arobase (RFC 6531). |
| `denied_domains` | `[]` | Domaines refusés, avec leurs sous-domaines. |
| `allowed_domains` | `[]` | Les seuls domaines acceptés, avec leurs sous-domaines ; une liste vide accepte tous les domaines. |
| `disposable_domains` | `[]` | Domaines jetables ajoutés à la liste que la bibliothèque importe. |
| `role_local_parts` | la liste du bundle | Les parties avant l'arobase qui désignent un rôle : on avertit, on ne refuse jamais. |
| `fingerprint_key` | `%kernel.secret%` | Le secret dont dérive la clé des empreintes des adresses, par HKDF-SHA256 sous `phil_email_address.fingerprint` : les empreintes ne correspondent jamais à un hachage que garde, pour l'adresse, un autre usage du même secret. |

`sites` et `lists` prennent les mêmes options, sauf `disposable_domains`, `role_local_parts` et `fingerprint_key`.
Chaque option qu'ils omettent, ou qu'ils fixent à `null`, garde la valeur de la politique en dessous : celle d'un site
est superposée à celle du bundle, celle d'une liste à celle du site. Les domaines refusés s'additionnent.
`allowed_domains` est celle de la politique la plus précise qui la nomme : la liste vide d'une liste accepte de nouveau
tous les domaines.

`current_site` est l'identifiant d'un service qui implémente `CurrentSite`, dont la clé choisit la politique de
`sites` ; sans lui, aucune politique de site ne s'applique. Un formulaire ou une contrainte qui nomme une liste que
`lists` ne contient pas lève `UnknownEmailList`.

La politique s'applique à `AcceptedEmailAddress` et à `EmailAddressType`. Le `DeliverableEmailAddress` de la
bibliothèque, employé directement, garde les options que donne son attribut et ne reçoit que les listes et le résolveur
du conteneur.

## Le DNS

| Option | Valeur par défaut | Effet |
|---|---|---|
| `dns.resolver` | `null` | L'identifiant d'un service qui implémente `MailDomainResolver` ; `null` interroge le résolveur du système par `dns_get_record()`. |
| `dns.cache` | `cache.app` | Le groupe de cache qui garde les réponses ; `null` n'en garde aucune. Une recherche échouée est gardée 60 secondes. |
| `dns.ttl` | `3600` | La durée de conservation d'une réponse, en secondes. |

## Les suggestions

| Option | Valeur par défaut | Effet |
|---|---|---|
| `suggestions.domains` | la liste de la bibliothèque | Les domaines suggérés, en ASCII minuscule, les plus utilisés d'abord ; le serveur et le navigateur emploient la même liste. |
| `suggestions.url` | `null` | L'URL de la route `phil_email_address_domains` ; `null` laisse `EmailAddressType` sans son contrôleur Stimulus. |

## Les rebonds

| Option | Valeur par défaut | Effet |
|---|---|---|
| `bounces.enabled` | `true` | Enregistre les règles de rebond et leurs entrées ; `false` n'en enregistre aucune. |
| `bounces.soft_bounce_threshold` | `3` | Les rebonds temporaires dans la fenêtre qui suspendent une adresse. |
| `bounces.soft_bounce_window` | `P30D` | La fenêtre glissante où l'on compte les rebonds temporaires, une durée ISO 8601 positive. Un avis n'est aussi décidé qu'une fois dans cette fenêtre. |
| `bounces.history` | `cache.app` | Le groupe de cache qui garde les avis décidés et les rebonds temporaires. Il doit garder ses éléments jusqu'à leur expiration et être partagé par chaque serveur qui reçoit des rebonds. |
| `bounces.authserv_ids` | `[]` | Les noms sous lesquels votre serveur de courriel écrit `Authentication-Results`. |
| `bounces.feedback_domains` | `[]` | Les domaines de signature DKIM des boucles de rétroaction auxquelles vous êtes abonné. Avec `authserv_ids`, un rapport d'abus signé par l'un d'eux est authentifié. |
| `bounces.authenticator` | `null` | L'identifiant d'un service qui implémente `ReportAuthenticator`, à la place des deux options précédentes. |
| `bounces.verp` | désactivé | Le chemin de retour avec lequel les messages sont envoyés ; une étiquette signée avec `key` désigne le destinataire. `domain` est obligatoire quand il est activé. |
| `bounces.remote_event_consumers` | `[]` | Les noms des webhooks, sous `framework.webhook.routing`, dont les règles consomment les événements de courriel. |
| `bounces.recipient_metadata` | `null` | La clé des métadonnées du message qui contient l'identifiant du destinataire, dans les événements d'un webhook. |
| `bounces.message_bus` | `null` | L'identifiant d'un bus de messages où chaque décision est transmise, sous forme de `BounceDecidedMessage` sans l'adresse. |
| `bounces.mailbox` | `null` | L'identifiant d'un service qui implémente `BounceMailbox`, que lit `phil:email-address:poll-mailbox`. |
| `bounces.maildir` | `null` | Un Maildir où le serveur de courriel livre les rebonds, lu à la place de `mailbox`. |

Sans `authserv_ids` ni `feedback_domains`, et sans `authenticator`, chaque rapport d'abus va à la révision : n'importe
qui peut en écrire un dans la boîte des rebonds.
