<!-- phil-dev source-sha256=b4cf195e7ebae2887ebccaefc3e988dde3ff8388f6a457c985b602fcce340b88 -->
# JSON-LD

## Le document

`JsonLd::encode(Graph $graph, bool $pretty = false)` écrit le graphe dans un seul document JSON-LD :

```json
{"@context":"https://schema.org","@graph":[{"@type":"WebSite","@id":"https://example.com/#website","name":"Le Gnome","url":"https://example.com/"}]}
```

Un graphe doté d'une langue l'écrit dans son contexte, après celui de schema.org :

```json
{"@context":["https://schema.org",{"@language":"fr-CA"}],"@graph":[…]}
```

Un nœud seul est quand même écrit sous `@graph` : le document a une seule forme, quel que soit son contenu.

## L'élément de script

`JsonLd::script(Graph $graph, ?string $nonce = null)` donne l'unique élément qu'une page imprime, ou un texte vide pour
un graphe vide :

```html
<script type="application/ld+json" nonce="…">{"@context":"https://schema.org","@graph":[…]}</script>
```

Les données ne peuvent ni terminer l'élément, ni y ouvrir un commentaire, ni dérouter un analyseur :

| Écrit | Sous la forme |
|---|---|
| `<`, `>` | `<`, `>` : `</script>` et `<!--` n'apparaissent jamais |
| `&` | `&` |
| U+2028, U+2029 | ` `, ` ` |
| Un texte qui n'est pas de l'UTF-8 valide | U+FFFD, plutôt qu'une page en échec |
| `/`, lettres hors ASCII | Eux-mêmes, pour que le document reste lisible |

Le *nonce* est celui de la politique de sécurité du contenu de la page, fourni par l'application. C'est un texte en
base64 de 1 à 256 caractères ; tout le reste est refusé plutôt qu'échappé. Un navigateur n'exécute aucun JSON-LD, mais
une politique stricte ou un outil d'audit peut quand même exiger un *nonce* sur chaque élément de script.

`JsonLd::FLAGS` contient les indicateurs de `json_encode()` avec lesquels la bibliothèque écrit.

## Relire une page

`JsonLdReader` lit ce que contient une page, pour le vérifier ou le comparer avec ce que l'application voulait
imprimer :

| Membre | Donne |
|---|---|
| `JsonLdReader::fromHtml(string $html)` | Une `Extraction` : le graphe de tous les éléments JSON-LD fusionnés par `@id`, leur nombre, et la raison pour laquelle chaque élément illisible a été écarté |
| `JsonLdReader::fromJson(string $json)` | Le graphe d'un document : un nœud, une liste de nœuds, ou un objet avec un `@graph` ; le `@language` de son contexte devient celui du graphe |

Un élément est lu quand son `type` est `application/ld+json`, quelles que soient la casse et les espaces. Un élément qui
n'est pas du JSON, qui n'est pas un graphe de nœuds ou qui contient ce qu'un nœud refuse est écarté et nommé dans
`Extraction::$errors` ; les autres sont lus quand même.

Le lecteur lit des pages écrites par n'importe qui ; son travail est donc borné :

| Borne | Valeur |
|---|---|
| Une page | 5 000 000 octets (`MAX_HTML_BYTES`) ; une page plus grande est refusée |
| Éléments JSON-LD lus | 64 (`MAX_BLOCKS`) ; les autres sont écartés, avec une erreur |
| Profondeur du JSON | 64 niveaux (`MAX_JSON_DEPTH`) |
| Profondeur des nœuds | 32 niveaux (`Node::MAX_DEPTH`) |
| Nœuds dans le graphe | 10 000 (`Graph::MAX_NODES`) |

Le HTML est analysé par l'analyseur HTML5 de l'extension DOM de PHP, erreurs étouffées : une page est lue comme un
navigateur la lit.
