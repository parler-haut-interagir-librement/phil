<!-- phil-dev source-sha256=7ddcbea13524656f0c63d122cd68b00011a76d04239d4c207d783f8c3aed6801 -->
# Sécurité et vie privée

## Le germe

Le germe fixe l'avatar, et quiconque voit l'avatar voit quelque chose du germe. C'est un identifiant opaque et stable de
l'application : un UUID, ou une valeur aléatoire gardée sur le profil du membre, que celui-ci peut tirer de nouveau
pour changer son avatar.

Ce n'est jamais une adresse courriel ni son empreinte. Un service d'avatars indexé sur l'empreinte de l'adresse dit à
quiconque voit l'avatar à quelle adresse il appartient : un dictionnaire d'adresses retrouve l'empreinte. `Seed` refuse
tout ce qui contient une `@`, et tout ce qui n'est pas de 1 à 255 caractères ASCII visibles.

Les choix d'un avatar viennent de SHA-256 du germe et d'un compteur : ils répartissent les germes sur les couleurs et
les formes, et ne sont pas un secret. Un avatar ne révèle pas son germe, mais n'employez pas comme germe une valeur qui
doit rester secrète.

## Un balisage sûr par construction

Un avatar est écrit par la bibliothèque seule. Les styles — ceux du paquet et ceux de l'application — dessinent par
`Canvas`, qui écrit lui-même chaque élément :

- les nombres sont écrits à partir de flottants, avec deux décimales au plus, et tout ce qui n'est pas fini s'écrit `0` ;
- les couleurs sont écrites à partir de `Color`, que seul `#rrggbb` atteint ;
- le texte — initiales, étiquette — est échappé pour XML, guillemets compris, et l'UTF-8 invalide est remplacé ;
- l'identifiant d'une découpe est une empreinte écrite en hexadécimal.

Aucune chaîne de l'appelant n'atteint le document comme balisage : un nom comme `"><script>` est dessiné comme ses
lettres et nommé comme son texte. Les tests analysent chaque avatar comme du XML et vérifient qu'il ne contient aucun
élément que la bibliothèque n'écrit pas ; la cible de fuzzing `initials` fait de même sur des noms que personne n'a
écrits.

## Des bornes sur chaque entrée

| Entrée | Borne |
|---|---|
| Un germe | De 1 à 255 caractères ASCII visibles, sans `@` |
| Un nom | Quelconque ; lu à partir de son premier kilo-octet |
| Une étiquette | UTF-8 valide, 200 caractères au plus |
| Une taille | De 16 à 1024 pixels |
| Des initiales | De 1 à 3 lettres |
| Une palette | De 1 à 64 fonds, chacun lisible avec une encre |
| Le nom d'un style | De 1 à 32 lettres minuscules, chiffres ou `-` |

Le travail d'un avatar est fixé par son style — une poignée d'éléments —, non par son entrée.
