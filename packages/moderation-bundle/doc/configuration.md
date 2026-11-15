# Configuration

The bundle's configuration lives under `phil_moderation`:

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
    incident:
        role: ROLE_ADMIN
        presets:
            - { name: quiet-listings, label: Suspend new listings, restrictions: [new-listings], default_minutes: 240, maximum_minutes: 2880 }
        without: [read-only]
        spare_established: [new-posts, new-comments]
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

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the bundle's services; `false` registers none. |
| `connection` | `default` | The DBAL connection of the tables, by its name in the Doctrine configuration. |
| `reasons` | the nine above | The reasons a report form offers, in order; codes of lowercase letters, digits, `-` and `_`. Their labels are translated as `reason.<code>` in the domain `phil_moderation`. |
| `roles` | `moderator` (`ROLE_MODERATOR`), `administrator` (`ROLE_ADMIN`) | The roles of the moderation, in the order they are tried: a member who holds several acts under the first. `types` lists the target types a role decides on; every type when empty. The public journal shows the name, translated as `role.<name>`. |
| `admin.layout` | `@PhilModeration/layout.html.twig` | The template the pages extend; it defines the blocks `title` and `main`. |
| `admin.page_size` | `25` | Lines of the queue per page, 1 to 200. |
| `journal.page_size` | `50` | Lines of the public journal per page, 1 to 200. |
| `sandbox.types` | none | The target types whose first content of a member waits for the moderation. |
| `surge.reports`, `surge.within` | `25`, `3600` | When this many reports come within this many seconds (60 to 86,400), the back office proposes the incident mode. |
| `incident.role` | `ROLE_ADMIN` | The security attribute that may start and lift an incident. |
| `incident.presets` | none | The application's presets, after the built-in ones; one of the same name replaces it. Durations in minutes. |
| `incident.without` | none | The built-in presets to leave out. |
| `incident.spare_established` | none | The restrictions an incident leaves established members free from, as the port `EstablishedMembers` of `phil/incident-mode` names them ([Incident mode](incident-mode.md#established-members)). |
| `statements.enabled` | `true` | Whether a decision that restricts the author comes with a statement of reasons ([Statements of reasons](statements-of-reasons.md)); `statements: false` turns them off. |
| `statements.grounds` | none | What each reason rests on, by its code: `ground` is `terms` or `illegal`, `reference` the provision a statement names by default. A reason not listed rests on the terms; `illegal` rests on the law. |
| `statements.terms_url`, `statements.complaint_url` | `null` | The terms of the site and the page where a member contests a decision: a path of the site or an `https` address. |
| `statements.out_of_court` | none | The out-of-court dispute settlement bodies the site accepts, each a `name` and a `url`. |
| `statements.notification_type` | `null` | The type of `phil/notifications-bundle` that tells a member a statement is addressed to them; no notification when `null`. |
| `statements.page_size` | `25` | Statements per page on the member's list, 1 to 200. |
| `kit_subdirectory` | `null` | Where the recipes of the kit are, under the components directory; the subdirectory of `ux_toolkit` when `null`. |
