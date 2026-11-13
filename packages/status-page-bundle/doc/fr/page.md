<!-- phil-dev source-sha256=04d6d1055290226865e011e0599b73c289a1a759c59e1d5dbf8fedaa1064059e -->
# La page

`config/routes.php` déclare trois routes, qui répondent chacune à `GET` et `HEAD` :

| Route | Chemin | Réponse |
|---|---|---|
| `phil_status_page` | `/status` | La page, `text/html` |
| `phil_status_page_json` | `/status.json` | Le JSON, `application/json` |
| `phil_status_page_atom` | `/status.atom` | Le flux Atom, `application/atom+xml` |

Chaque réponse porte `Cache-Control: public, max-age=<cache_max_age>` — `no-cache` avec 0 — et
`X-Content-Type-Options: nosniff` ; le JSON et le flux portent `Access-Control-Allow-Origin: *`, de sorte que la page
d'un autre site peut les lire. Aucune réponse ne lit la session ni ne pose de témoin, et la page ne charge rien d'un
autre site.

## Ce que montre la page

1. Le titre et l'état général : le pire état des composants, opérationnel s'il n'y en a aucun.
2. Les incidents en cours, le plus récent d'abord : titre, impact, état, les composants qu'ils touchent, et leurs mises
   à jour, la plus récente d'abord, chacune avec son état, son moment en UTC et son message.
3. Les maintenances en cours, puis à venir, la plus proche d'abord : titre, fenêtre en UTC, composants et description.
4. Les composants, regroupés sous leur `group` dans l'ordre où le premier membre de chaque groupe a été déclaré, chacun
   avec son état.
5. Les incidents résolus dans les `history_days` derniers jours, le plus récent d'abord.
6. Le moment où la page a été assemblée, et des liens vers le flux et le JSON.

Chaque partie est une `<section>` étiquetée par son titre ; les incidents, les maintenances et les composants sont des
éléments de liste avec les identifiants `incident-<id>`, `maintenance-<id>` et `component-<id>`, les fragments vers
lesquels le flux pointe. Un état est un `Badge` avec son nom écrit : `outline` quand il est opérationnel, `secondary` en
maintenance ou dégradé, `destructive` partiellement en panne ou en panne. La page ne porte aucun script ni aucun style
propre ; la feuille de style du kit, chargée par la mise en page de l'application, donne leur apparence aux recettes.

Les gabarits sont `@PhilStatusPage/page.html.twig` et ses partiels `_badge`, `_moment`, `_incident` et `_maintenance`,
dans `layout`. Les textes viennent du domaine de traduction `phil_status_page`, en anglais et en français ; une autre
langue est un fichier `translations/phil_status_page.<locale>.php` (ou `.yaml`, `.xlf`) de l'application, avec les clés
du fichier anglais.

## L'état d'un composant

1. La source donne un état : le `status` déclaré, ou les vérifications de `phil/monitoring-bundle` (plus bas). Un
   composant que la source omet est opérationnel.
2. Tant qu'une maintenance en cours nomme le composant, il est en maintenance, quoi que la source ait dit.
3. Chaque incident en cours qui le nomme fait de son état le pire des deux, dans l'ordre opérationnel, en maintenance,
   dégradé, partiellement en panne, en panne.

### Tiré de phil/monitoring-bundle

`MonitoringStatuses` lit `MonitoringStatus`, le modèle de lecture de `phil/monitoring-bundle` :

| Ce que dit la surveillance | Le composant |
|---|---|
| Chaque vérification qu'il nomme est bonne, chaque tâche qu'il nomme est à l'heure | Opérationnel |
| La pire de ses vérifications est dégradée, ou l'une de ses tâches est en retard | Dégradé |
| La pire de ses vérifications est en panne | En panne |
| Le mode maintenance du site est actif | En maintenance, chaque composant |
| Il ne nomme aucune vérification ni aucune tâche que la surveillance connaît | Son `status` déclaré |

Une tâche qui n'a jamais battu ne change rien. Les vérifications s'exécutent une fois par `monitoring.cache_ttl`
secondes au plus : les états sont conservés dans `monitoring.cache_pool` comme valeurs simples sous une seule clé, de
sorte que la page publique n'exécute jamais les vérifications à chaque vue.

## Le JSON

```json
{
    "version": "1",
    "page": { "title": "Example status", "url": "https://example.org/status", "generated_at": "2026-11-12T12:00:00+00:00" },
    "status": "partial_outage",
    "components": [
        { "id": "api", "name": "API", "description": null, "group": "Platform", "status": "partial_outage" }
    ],
    "incidents": [
        {
            "id": "api-errors", "title": "Errors on the API", "impact": "partial_outage", "state": "identified",
            "components": ["api"], "started_at": "…", "updated_at": "…", "resolved_at": null,
            "updates": [{ "at": "…", "state": "identified", "message": "…" }]
        }
    ],
    "maintenances": [
        { "id": "upgrade", "title": "Upgrade", "description": null, "state": "upcoming", "components": ["api"], "starts_at": "…", "ends_at": "…" }
    ],
    "history": []
}
```

Les états sont `operational`, `maintenance`, `degraded`, `partial_outage` et `major_outage` ; les états d'incident
`investigating`, `identified`, `monitoring` et `resolved` ; une maintenance est `in_progress` ou `upcoming`. Les dates
sont en ISO 8601 en UTC. `incidents` contient ceux qui sont en cours, `history` ceux qui sont résolus dans l'historique.
La forme est conservée au sein d'une `version`.

## Le flux Atom

Le flux (RFC 4287) a une entrée par incident en cours ou dans l'historique, puis par maintenance en cours ou à venir.
L'identifiant et le lien d'une entrée sont l'adresse de sa partie de la page, `…/status#incident-<id>`, de sorte qu'un
lecteur voit une mise à jour comme un changement de la même entrée ; sa `category` est l'état de l'incident, ou
`maintenance` ; son contenu est du texte brut, chaque mise à jour avec son moment et son état. Le flux est daté par son
entrée la plus récente, sinon par la page.
