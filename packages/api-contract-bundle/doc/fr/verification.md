<!-- phil-dev source-sha256=796fd82e5dfe09f01ebd498fa17c8785818484bb65c486c1f25dbd2bbe392a3d -->
# Vérification

`phil:api-contract:verify` vérifie le document OpenAPI de l'application selon un niveau de règles de
`phil/api-contract`, imprime chaque constat et échoue quand l'un d'eux atteint le seuil. Exécutée en intégration
continue, elle garde l'API telle que son document la décrit, et le document tel que le contrat le demande : REST, JSON,
aucune version dans un chemin, des détails de problème pour les erreurs.

```bash
bin/console phil:api-contract:verify openapi.yaml
# warning json-content at /paths/~1orders/get/responses/200/content/text~1csv: The media type "text/csv" is not JSON.
# 0 error(s), 1 warning(s) at the recommended level.
```

Les niveaux, les dix-sept règles et leurs gravités sont décrits avec la bibliothèque, dans sa documentation de la
vérification OpenAPI.

## Arguments et options

| Argument ou option | Valeurs | Valeur par défaut |
|---|---|---|
| `document` | Un fichier JSON, ou un fichier YAML dont le nom se termine par `.yaml` ou `.yml` | `verification.document` |
| `--level` | `minimal`, `recommended`, `strict` | `verification.level`, `recommended` |
| `--fail-on` | `error`, `warning` | `verification.fail_on`, `error` |
| `--format` | `text`, `json` | `text` |

`--format=json` écrit le rapport sous la forme `{"level": …, "findings": [{"rule", "severity", "location", "message"}]}`,
pour une étape d'intégration continue qui annote le document.

## Codes de sortie

| Code | Quand |
|---|---|
| `0` | Aucun constat n'atteint le seuil. |
| `1` | Un constat atteint le seuil : une erreur, ou avec `--fail-on=warning` une erreur ou un avertissement. |
| `2` | Aucun document n'est nommé ni configuré, une option a une valeur qu'elle n'accepte pas, ou le document est illisible : absent, de plus de 8 Mio, ni JSON ni YAML, de plus de 128 niveaux de profondeur. |

Un document YAML exige `symfony/yaml` ; sans lui, la commande répond `2` et le dit. Le texte du document est imprimé
échappé : un chemin ou une référence qui contient des balises de console ou des caractères de contrôle est montré, pas
obéi.

## Configuration

```yaml
# config/packages/phil_api_contract.yaml
phil_api_contract:
    verification:
        document: '%kernel.project_dir%/openapi.yaml'
        level: strict
        fail_on: warning
        skip: [operation-description]
```

`skip` nomme les règles que le vérificateur écarte ; un nom qui n'est l'identifiant d'aucune règle fait échouer la
construction du vérificateur, pour qu'une faute de frappe ne garde pas une règle en silence. Voyez
[Configuration](configuration.md).

## Les règles propres à l'application

Un service qui implémente `Phil\ApiContract\Contract\OpenApiRule` reçoit l'étiquette `phil_api_contract.openapi_rule`
par autoconfiguration et s'exécute après les règles intégrées, au niveau qu'il déclare. Sans autoconfiguration,
étiquetez-le :

```yaml
# config/services.yaml
services:
    App\Api\PluralCollections:
        tags: [phil_api_contract.openapi_rule]
```

La documentation des points d'extension de la bibliothèque donne le contrat d'une règle et un exemple.

## Dans un pipeline d'intégration continue

```yaml
# a step of the pipeline
- run: bin/console phil:api-contract:verify --level=strict --fail-on=warning
```

Le vérificateur est le service public `Phil\ApiContract\OpenApi\ContractVerifier` : un test de l'application peut
vérifier de la même façon un document qu'elle génère.
