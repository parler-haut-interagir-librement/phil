<!-- phil-dev source-sha256=6d3cce9fa568a1870cb2699214afc034f8a12bab670393571e63af8237441acd -->
# Vie privée

## Les abonnés cachés

Chaque membre choisit si la liste de ses abonnés est montrée : `Followers::hideFollowers($member, true)`, ou le
formulaire de `@PhilFollow/visibility.html.twig`. Un membre qui n'a jamais choisi suit `followers.hidden_by_default`.

Le choix s'applique au sujet de type membre, `member_subject_type`. Quand elle est cachée, `Followers::followersOf()`
retourne une liste vide marquée cachée, et `Followers::count()` null, à tous sauf au membre : ni les noms ni le nombre
ne quittent le bundle, par ses services ou par ses gabarits. Les autres sujets — un fil, une annonce — montrent
toujours leurs abonnés.

## L'export et l'effacement

Avec phil/privacy-bundle, `FollowPrivacy` participe aux demandes d'un membre en tant que module `follows`, par les
contrats `PersonalDataExporter` et `PersonalDataEraser`.

L'export contient ce qui appartient au membre :

| Clé | Ce qu'elle liste |
|---|---|
| `follows` | Chaque sujet qu'il suit, son niveau, ses préférences par événement et depuis quand |
| `requests_sent` | Les demandes qu'il a envoyées et qui attendent encore |
| `requests_received` | Les demandes qui attendent sa réponse |
| `mutual_relationships` | L'autre membre de chaque relation, et depuis quand |
| `followers_hidden` | Son choix, quand il en a fait un |

Qui suit le membre appartient à chaque abonné, et est laissé de côté.

L'effacement supprime les suivis du membre et les suivis de son sujet de type membre, les demandes qu'il a envoyées ou
qu'il doit trancher, ses relations, les activités qu'il a faites, les revendications et les marques de ses livraisons
et son choix, et le retire des mentions des autres activités. Chaque dépôt est déclaré `deleted`. Exécuté deux fois,
l'effacement déclare la même chose.

## Ce qui voyage

Un message `FanOutActivity` porte l'identifiant d'une activité et d'un membre, rien d'autre ; le worker relit le reste.
Les notifications ne portent que des identifiants, comme phil/notifications-bundle l'exige.
