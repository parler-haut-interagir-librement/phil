<!-- phil-dev source-sha256=d608633613a15a9f2befc329080394b7113ae9ccb77ba0517bb117ee247b4cc8 -->
# Référence de l'API

Chaque type sous `Phil\AuditViewer\`, par couche.

## Bundle

| Type | Rôle |
|---|---|
| `PhilAuditViewerBundle` | `AbstractBundle`, alias `phil_audit_viewer` ([configuration.md](configuration.md)) |

## Infrastructure

| Type | Rôle |
|---|---|
| `EasyAdmin\AuditViewerController` | Les pages : `log()`, `row()`, `history()`, `chain()`, `verify()`, `export()`; `CSRF_ID` |
| `EasyAdmin\AuditViewerAdmin` | `menuItems($dashboardRoute)` : les liens vers le journal et la chaîne |
| `EasyAdmin\AuditHistoryActions` | `ActionsExtensionInterface` d'EasyAdmin : `supports()`, `extend()`, `action($route)`; `NAME = 'phil_audit_history'`, `PAGES` |
| `Http\AuditFilters` | Les filtres lus d'une chaîne de requête ou d'un formulaire : `read()`, `values`, `query`, `errors`, `parameters()`, `journal()`; `FIELDS` |
| `Csv\AuditCsv` | `write($stream, $rows, $limit)`, `cell()`; `HEADER` |
| `Security\AuditViewerVoter` | Accorde `VIEW` (`PHIL_AUDIT_VIEW`), `HISTORY` (`PHIL_AUDIT_HISTORY`) et `EXPORT` (`PHIL_AUDIT_EXPORT`) à leurs rôles quand la politique d'accès l'autorise, avec la raison; refuse tout le reste |
| `Security\Contract\AuditAccessPolicy` | Port : `allows($attribute, $subject, $token)`, ce que l'application refuse à un titulaire des rôles |

## Erreurs

Un filtre qui ne se lit pas est signalé par `AuditFilters` plutôt que levé. Le contrôleur répond avec
l'`AccessDeniedException` de Symfony sans l'attribut, avec `NotFoundHttpException` pour une ligne ou un segment qui
n'existe pas et pour l'export sans piste, et avec `BadRequestHttpException` pour un historique, un curseur ou un export
qui ne se lit pas.
