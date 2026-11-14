<!-- phil-dev source-sha256=a89ba08a89d3b343b246f26544ea644f9390827fd78a698d8a2ab42bb3057af8 -->
# Configuration

```yaml
# config/packages/phil_audit_viewer.yaml
phil_audit_viewer:
    role: ROLE_AUDIT_VIEWER          # reads the log
    export_role: ROLE_AUDIT_EXPORT   # exports it; the viewer's role when null
    history_role: ROLE_ADMIN         # reads the history of one record, besides the viewer's role
    access_policy: null              # an AuditAccessPolicy that narrows the roles, record by record
    history_action: true             # a "History" action on the CRUD pages of Auditable entities
    export_segment: audit            # the segment each export is recorded in
    export_limit: 100000             # the most rows one export reads and writes
    page_size: 50                    # the rows of a page of the log or of a history
    timezone: Europe/Paris           # instants shown and filtered in this time zone; PHP's default when null
```

| Clé | Valeur par défaut | Effet |
|---|---|---|
| `role` | `ROLE_AUDIT_VIEWER` | Le rôle auquel `AuditViewerVoter` accorde `PHIL_AUDIT_VIEW`; il ne peut pas être vide |
| `export_role` | `null` | Le rôle auquel il accorde `PHIL_AUDIT_EXPORT`; avec `null`, le rôle du lecteur |
| `history_role` | `null` | Un rôle auquel il accorde `PHIL_AUDIT_HISTORY`, en plus du rôle du lecteur; avec `null`, le rôle du lecteur seulement |
| `access_policy` | `null` | L'id de l'`AuditAccessPolicy` de l'application, que le *voter* interroge une fois un rôle détenu; elle restreint les rôles, ne les élargit jamais ([extension-points.md](extension-points.md)) |
| `history_action` | `true` | Si `AuditHistoryActions` ajoute l'action « Historique » aux pages CRUD des entités qui implémentent `Auditable` ([history-action.md](history-action.md)) |
| `export_segment` | `audit` | Le segment de la piste où chaque export est consigné : de 1 à 64 lettres minuscules, chiffres, `.`, `_` ou `-` |
| `export_limit` | `100000` | Le plus grand nombre de lignes qu'un export lit, et donc écrit, au moins 1; une ligne que la politique d'accès refuse est lue mais n'est pas écrite |
| `page_size` | `50` | Les lignes d'une page, de 1 à 500 |
| `timezone` | `null` | Le fuseau horaire dans lequel les instants sont montrés et la période filtrée; avec `null`, le fuseau horaire par défaut de PHP à la construction du conteneur |

Une clé inconnue ou une valeur hors des bornes est refusée à la construction du conteneur.

## Services câblés

| Identifiant de service | Public | Définition |
|---|---|---|
| `AuditViewerController` | oui | Avec Twig, `AuditLog`, `ChainStatus`, le vérificateur d'autorisation, le gestionnaire de jetons CSRF, l'horloge, les clés ci-dessus, et `AuditTrail`, `ActorResolver` et `PayloadReader` quand il y en a; étiqueté comme contrôleur de route d'administration d'EasyAdmin |
| `AuditViewerVoter` | non | Avec le gestionnaire de décision d'accès, le rôle du lecteur, le rôle d'export et `history_role` et le service `access_policy`; étiqueté `security.voter` |
| `AuditHistoryActions` | non | Avec le routeur et le vérificateur d'autorisation, quand `history_action` est activé; étiqueté `ea.actions_extension` |
