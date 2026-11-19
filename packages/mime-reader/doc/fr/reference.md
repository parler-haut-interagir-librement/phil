<!-- phil-dev source-sha256=d949b8b176d3eb512457b99522c6e0ea4a085b5e29656a184e3458ffa8b33b1b -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `MimeReader` | Lit un message brut, depuis une chaîne ou un flux, en son arbre de parties |
| `Limits` | Les octets, la profondeur, les parties, les champs d'en-tête et les paramètres lus dans un message |
| `Part` | Un message ou l'une de ses parties : champs d'en-tête, type, paramètres, corps décodé à la demande, parties enfants, message contenu |
| `Headers` | Les champs d'en-tête d'une partie ou d'un groupe de champs de rapport, dépliés, avec décodage, paramètres et listes d'adresses |
| `HeaderValue` | Une valeur d'en-tête avec ses paramètres, dont les sections et les jeux de caractères sont joints et décodés |
| `Mailbox` | Une adresse d'un champ d'adresses, avec son nom d'affichage décodé |
| `Exception\MimeReaderException` | Toute exception que lance le paquet |
| `Exception\InvalidLimits` | Une limite inférieure à 1 |
| `Exception\InvalidStream` | Une valeur donnée comme flux qui n'est pas un flux ouvert |

Les classes de `Internal\` sont propres au lecteur et peuvent changer dans n'importe quelle version.
