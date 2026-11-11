<!-- phil-dev source-sha256=ac42aea47edd63bb8faba26c8f7b6abb9b6c758848935b8b3713ed4f4d9c0d2e -->
# Servir et écrire

## Servi

`/sitemap.xml` est l'index : une entrée par fichier, à son adresse `/sitemap.{name}.xml` sur l'hôte de la requête,
avec le `lastmod` le plus récent de ses pages. `/sitemap.{name}.xml` construit seulement la section à laquelle
appartient le fichier — `news` pour `news` et pour `news_1` — et répond avec ses pages ; un fichier que le plan de
site n'a pas donne un `404`. Les deux sont en `application/xml`.

Chaque réponse porte ce qui permet à un cache de la garder :

| En-tête | Valeur |
|---|---|
| `Cache-Control` | `public, max-age=<cache_max_age>` ; privée et gardée par aucun cache quand `cache_max_age` vaut `0` |
| `Last-Modified` | le `lastmod` le plus récent des pages qu'elle liste, quand l'une a un `lastmod` |
| `ETag` | une empreinte du XML |

Une requête qui détient déjà la réponse — `If-None-Match` ou `If-Modified-Since` — reçoit un `304` sans corps. Un
mandataire inverse ou un CDN devant le site répond alors à la plupart des explorations sans que le plan de site soit
construit.

C'est la construction qui coûte. L'index demande chaque page à chaque fournisseur, un fichier à chaque fournisseur de
sa section, et les pages des routes lisent toute la collection de routes, que le routeur charge depuis ses ressources
à chaque requête. Le routeur garde la collection une fois chargée, tant qu'il vit : `RouteUrlProvider` la lui demande
donc chaque fois et n'en garde aucune copie à lui. Un worker de longue durée la charge une seule fois.
**Un site en production qui a plus de quelques centaines de pages écrit son plan de site en fichiers** et laisse le
serveur Web servir les fichiers, en laissant les routes non importées ou en solution de repli.

## Écrit

```bash
bin/console phil:sitemap:dump                          # into dump_directory
bin/console phil:sitemap:dump var/sitemap --gzip
bin/console phil:sitemap:dump --base-url=https://cdn.example.com
```

| Écrit | |
|---|---|
| `sitemap.{name}.xml` | un par fichier ; `sitemap.{name}.xml.gz` avec `--gzip` |
| `sitemap.xml` | l'index, jamais compressé, qui pointe vers les fichiers sous l'URL de base |

L'URL de base est celle du routeur — `framework.router.default_uri` — sauf si `--base-url` en donne une autre. En
ligne de commande, le routeur ne connaît aucun hôte à lui : **sans `default_uri` et sans `--base-url`, la commande
refuse d'écrire**, puisque l'index enverrait les moteurs de recherche vers `http://localhost`.

Rien n'est écrit avant que le plan de site soit construit : un fournisseur qui échoue — une base de données en panne,
une mauvaise option de route — laisse l'écriture précédente entière. Chaque fichier est ensuite écrit sous un nom
temporaire à côté de sa place, puis renommé, l'index en dernier : un robot d'exploration ne lit jamais un demi-fichier,
ni un index qui nomme un fichier pas encore là. Une écriture qui échoue — un disque plein, un répertoire non
inscriptible — arrête la commande avec une erreur, supprime les fichiers temporaires et garde l'index précédent. Enfin,
les fichiers `sitemap.*.xml` et `sitemap.*.xml.gz` d'une écriture précédente que la nouvelle n'a pas sont supprimés :
une section disparue ne laisse aucun fichier derrière elle ; les autres fichiers du répertoire restent. Un déploiement
lance l'écriture une fois la version en place.
