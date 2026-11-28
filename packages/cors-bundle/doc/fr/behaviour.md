<!-- phil-dev source-sha256=e5b896da13c197a1e74c975382321f25be27fb7ab8ed888f8731033389960c7a -->
# Comment les requêtes reçoivent leur réponse

Le bundle enregistre un seul écouteur, `phil_cors.listener`, sur deux événements du noyau. Les deux ne regardent que les
requêtes principales : une sous-requête, comme un fragment ou un contrôleur d'erreur, est laissée telle quelle.

## Préambules

Un préambule est une requête `OPTIONS` avec un en-tête `Origin` et un en-tête `Access-Control-Request-Method`. Sur
`kernel.request`, à la priorité 250, avant le routeur (32) et le pare-feu (8), l'écouteur trouve la politique du
chemin. Sans politique, la requête continue comme toute autre. Avec une politique, le bundle répond aussitôt, et aucun
contrôleur, aucune route ni aucun authentificateur ne s'exécute : un préambule ne porte ni témoin ni identifiant de
connexion, et n'a pas besoin de route `OPTIONS`.

La réponse est toujours `204 No Content`, avec :

| En-tête | Quand |
|---|---|
| `Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers` | Toujours ; `Access-Control-Request-Private-Network` s'y ajoute quand le chemin autorise le réseau privé. |
| `Access-Control-Allow-Origin` | L'origine, la méthode et chaque en-tête demandé sont autorisés : l'origine, ou `*` quand le chemin autorise toute origine. |
| `Access-Control-Allow-Credentials: true` | Autorisé, et le chemin autorise les identifiants de connexion. |
| `Access-Control-Allow-Methods` | Autorisé : les méthodes du chemin. |
| `Access-Control-Allow-Headers` | Autorisé : les en-têtes du chemin, ou ceux demandés quand le chemin les autorise tous ; non envoyé quand il n'y en a aucun. |
| `Access-Control-Max-Age` | Autorisé, et le chemin donne `max_age`. |
| `Access-Control-Allow-Private-Network: true` | Autorisé, le chemin l'autorise, et la requête envoie `Access-Control-Request-Private-Network: true` ; Chrome ne l'envoie plus, voir [Le réseau privé](configuration.md#le-réseau-privé). |

Quand l'origine, la méthode ou l'un des en-têtes demandés n'est pas autorisé, la réponse n'a que `Vary` : le navigateur
lit l'absence de `Access-Control-Allow-Origin` comme un refus et n'envoie jamais la requête elle-même. La raison n'est
pas donnée à la page, puisque les navigateurs n'en donnent pas non plus.

## Autres réponses

Sur `kernel.response`, pour chaque réponse d'un chemin qui a une politique, pages d'erreur comprises, l'écouteur :

1. ajoute `Origin` à `Vary`, en gardant les valeurs déjà présentes, que la requête ait ou non un en-tête `Origin`, pour
   qu'un cache ne donne jamais la réponse d'une origine à une autre ou à une visite de même origine ;
2. retire `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials` et `Access-Control-Expose-Headers`, pour
   qu'un en-tête posé par un contrôleur n'ouvre jamais un chemin plus grand que sa politique ;
3. quand la requête a un `Origin` autorisé, pose `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials` quand
   le chemin autorise les identifiants de connexion, et `Access-Control-Expose-Headers` quand le chemin expose des
   en-têtes.

Une réponse à un chemin sans politique est laissée exactement comme l'application l'a faite.

## Ce qu'une requête peut envoyer

Le bundle borne ce qu'il lit, pour qu'une requête ne le fasse jamais travailler plus que quelques comparaisons :

| Valeur | Borne | Au-delà |
|---|---|---|
| `Origin` | 512 octets | L'origine n'est pas autorisée. |
| `Access-Control-Request-Headers` | 4096 octets et 64 noms | Le préambule est refusé. |

Une origine exacte se trouve dans une table de hachage ; les motifs sont essayés dans l'ordre de la configuration, sur
une valeur d'au plus 512 octets. La méthode est comparée sans tenir compte de la casse, tout comme les noms d'en-têtes ;
l'origine est comparée telle qu'envoyée.

## Workers de longue durée

L'écouteur, le service d'en-têtes et les politiques ne contiennent que la configuration, la même pour chaque requête :
rien n'est gardé d'une requête à la suivante, et le bundle n'a pas besoin de `kernel.reset`. Il s'exécute tel quel avec
le mode *worker* de FrankenPHP, RoadRunner ou tout autre environnement d'exécution qui sert plusieurs requêtes avec un
seul noyau.
