<!-- phil-dev source-sha256=56048a20a92cba550b3fb90abc5712cec37463ae6fc3b897842fa53749c6f668 -->
# Menaces

Une boîte de rebonds reçoit du courriel de n'importe qui. Ce qu'un message hostile peut tenter, et qui l'arrête :

| Menace | Protection |
|---|---|
| Un message construit pour épuiser la mémoire ou le temps : énorme, profondément imbriqué, des milliers de parties ou de champs | `Limits` borne les octets, la profondeur, les parties, les champs et le texte ; le lecteur ne lève jamais d'exception ; la cible de fuzzing exécute l'analyseur sur des messages que personne n'a écrits |
| Un faux rapport de plainte, pour désabonner quelqu'un | Une plainte ne désabonne que lorsque le `Contract\ReportAuthenticator` s'en porte garant ; `Auth\AuthenticationResults` ne fait confiance qu'aux résultats DKIM du propre serveur de l'application pour les domaines des boucles de rétroaction |
| Un faux rebond, pour compter des rebonds durs contre l'adresse de quelqu'un d'autre | L'application associe le rapport à un message qu'elle a envoyé — étiquette VERP, `Message-ID`, son propre champ — et ne compte qu'alors ; une étiquette écrite par `Verp::encodeSigned()` et vérifiée par `decodeSigned()` ne peut pas être falsifiée, alors qu'une étiquette VERP classique d'`encodeRecipient()` le peut et ne doit pas décider une suppression |
| Un faux champ d'enveloppe, pour qu'un rebond porte l'étiquette de quelqu'un d'autre | L'étiquette n'est lue que dans `Delivered-To`, `X-Original-To` et `Envelope-To`, qu'ajoute le serveur de réception, jamais dans `To` ; `envelopeFields` ne liste que les champs qu'écrit le propre serveur de l'application |
| L'expression régulière d'une règle qui fait des retours arrière sans fin | La limite de retours arrière de PCRE l'arrête ; une comparaison échouée ne correspond pas ; le texte que lit une règle est borné par `maxText` |
| Une expression régulière invalide dans la configuration | Refusée à la déclaration de la règle |
| Des renseignements personnels dans les journaux | Les exceptions nomment des règles et des limites, jamais des adresses ; le rapport contient des adresses pour l'application, qui les garde hors de ses messages et de ses journaux |

Ce qui reste à l'application : lire la boîte avec des identifiants de connexion qu'elle protège, supprimer ce qu'elle a
traité, associer les rapports à ses envois, les seuils et les listes de suppression, et effacer les messages qu'elle
garde en révision une fois qu'une personne les a lus.
