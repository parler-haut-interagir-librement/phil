<!-- phil-dev source-sha256=9fa365d5404c51b015e26ad07cbc690c8ab6bad77928b7370865af33119b6ccf -->
# Exceptions

Chaque exception du bundle implémente `Phil\PollBundle\Domain\Exception\PollBundleException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `Domain\Exception\InvalidPollDefinition` | `InvalidArgumentException` | Une question, une option ou un libellé hors bornes, des options autres que celles des règles, une méthode de dépouillement mal nommée ou qui ne compte pas le type de bulletin, des sièges hors bornes ou plus d'un pour une méthode qui n'en pourvoit qu'un, un sondage qui ferme au moment de son ouverture ou avant. |
| `Domain\Exception\AlreadyVoted` | `RuntimeException` | Un votant dépose un deuxième bulletin dans un sondage. |
| `Application\Exception\NotFound` | `RuntimeException` | Aucun sondage n'a cet identifiant, ou aucun bulletin du votant, ou du reçu, n'y est conservé. |
| `Application\Exception\VoteRefused` | `RuntimeException` | Le sondage n'est pas ouvert, la politique refuse le votant, un bulletin anonyme est modifié sans son reçu ou retiré, un bulletin nominatif est modifié avec un reçu. |
| `Application\Exception\PollRefused` | `RuntimeException` | La politique refuse de laisser l'acteur créer ou gérer le sondage, la personne qui regarde n'a pas encore le droit de voir ses résultats, ou une élection est demandée d'un sondage dont la méthode ne pourvoit aucun siège. |

Les exceptions de `phil/poll` passent à travers `Polls` : `InvalidBallot` quand les choix d'un bulletin enfreignent
les règles, ou quand `BallotForm` lit des champs que le formulaire n'envoie pas, `InvalidPoll` quand les options ou les bornes d'un brouillon les enfreignent, et `UnknownCountingMethod`
quand un sondage conservé nomme une méthode que l'application ne déclare plus.

Les messages de `VoteRefused`, `PollRefused`, `AlreadyVoted` et `InvalidBallot` sont écrits pour le votant et peuvent
lui être affichés sans risque.
