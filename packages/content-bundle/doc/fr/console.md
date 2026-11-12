<!-- phil-dev source-sha256=2ad475627740af7e6724d07f646d7517af19ec84498b9d2a54c41d8014782506 -->
# Commandes de console

À côté de l'administration ([administration.md](administration.md)), le contenu se modifie avec six commandes, dont
cinq sont enregistrées quand le noyau a DoctrineBundle. Chacune appelle `ContentManager`
([content-manager.md](content-manager.md)).

| Commande | Arguments | Options | Ce qu'elle fait |
|---|---|---|---|
| `phil:content:create` | `resource-key`, `locale` | `--file`, `--field`, `--json`, `--publish` | crée une racine du genre et son premier brouillon dans la locale, version 1, `unpublished` ; avec `--publish`, la publie ensuite — un corps qui perd du contenu en UJO est alors refusé, et le brouillon reste |
| `phil:content:edit` | `reference`, `locale` | `--file`, `--field`, `--json` | modifie le brouillon de la locale, en créant d'abord une nouvelle version si la version est publiée |
| `phil:content:publish` | `reference`, `locale` | `--accept-losses` | publie le brouillon de la locale ; ce que la conversion de son corps en UJO ne garde pas est d'abord listé, et on demande au rédacteur s'il veut publier quand même. Avec `--accept-losses`, il est publié sans qu'on le demande ; sans terminal pour le demander et sans l'option, la publication est refusée ([publication.md](publication.md#une-publication-qui-perd-du-contenu-est-refusée)) |
| `phil:content:unpublish` | `reference`, `locale` | — | met la locale hors ligne |
| `phil:content:preview-link` | `reference`, `locale` | — | affiche un lien signé vers le brouillon de la locale ([sitemap-feeds-previews.md](sitemap-feeds-previews.md)) ; exige TwigBundle |
| `phil:content:list` | `resource-key…` (facultatif, plusieurs) | — | une ligne par locale de la dernière version de brouillon de chaque racine : contenu, locale, version du brouillon, place, slug, version en ligne ; tous les genres déclarés quand aucun n'est donné |

Une `reference` s'écrit `resource_key:identifier` (`news:0192f3c4-…`, `static_page:12`).

```bash
bin/console phil:content:create news fr --file=news.yaml --field=slug=opening --publish
bin/console phil:content:edit news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70 fr --field=excerptTitle="The site opens"
bin/console phil:content:publish news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70 fr
bin/console phil:content:unpublish news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70 fr
bin/console phil:content:list news
```

## Les valeurs soumises

`create` et `edit` prennent les clés de la forme normalisée ([pipeline.md](pipeline.md#données-soumises)) dans trois
sources, appliquées dans cet ordre ; une option remplace donc le fichier :

1. `--file=<path>` : un fichier YAML (`.yaml`, `.yml`) ou JSON (`.json`) qui contient une table de clés vers des
   valeurs ;
2. `--field=key=value` (répétable) : une valeur en chaîne ; une clé à points définit une clé dans une table
   (`--field=templateData.title=Hello`) ;
3. `--json=key=<json>` (répétable) : une valeur typée — `--json=seoNoIndex=true`, `--json=slug=null`,
   `--json='seoKeywords=["larp","2026"]'`. Une clé à points fonctionne de la même façon.

```yaml
# news.yaml
templateKey: news
excerptTitle: Opening
excerptDescription: The site opens today.
seoKeywords: [opening, news]
bodySource: |
    The site **opens** today.
templateData:
    subtitle: A first article
```

Un fichier absent, une extension inconnue, un fichier qui ne s'analyse pas, un fichier qui n'est pas une table à
clés en chaînes, une option sans `=`, ou une valeur `--json` qui n'est pas du JSON est refusé avec un message
d'erreur.

## Sortie et codes de sortie

- En cas de réussite, chaque commande affiche un message de réussite ; `phil:content:create -q` affiche la référence
  seule, pour un script.
- **Un refus du domaine** — une `DomainError` : un slug déjà pris, une locale non servie, une transition non permise,
  une valeur refusée, une racine introuvable, une référence mal formée — s'affiche comme message d'erreur, et la
  commande se termine avec `Command::FAILURE`.
- Une erreur de configuration ou de code n'est pas un refus du domaine, et apparaît comme une exception : par exemple
  une clé de ressource qu'aucune racine déclarée n'a (`InvalidContentMapping`), ou une racine dont le constructeur
  exige des arguments sans `ContentRootFactory` de l'application.

`create --publish` exécute la création et la publication dans deux transactions : quand la publication est refusée,
la racine et son brouillon restent créés, et la commande se termine sur un échec.

## Classes

`Infrastructure\Console\ContentCommand` est la base abstraite des cinq commandes : elle contient le `ContentManager`,
déclare les options de données et transforme une `DomainError` en message d'erreur.
`Infrastructure\Console\SubmittedFields::read(?string $file, array $fields, array $json)` lit les valeurs soumises
comme décrit ci-dessus.
