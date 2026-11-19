<!-- phil-dev source-sha256=a7834f1199be9181c4bc95fea6b7bf35e9671148964f5090ddec5f583a695953 -->
# L'enveloppe de Sentry

Le point de terminaison des navigateurs lit son propre petit rapport, pas le format d'enveloppe des SDK de Sentry.
C'est un choix, maintenu pour ces raisons :

- **Les SDK envoient plus que ce que garde ce bundle.** Une enveloppe porte des fils d'Ariane, l'utilisateur, la
  requête avec ses témoins et son corps quand le SDK est configuré ainsi, les arguments des cadres, des pièces jointes
  et des sessions. La recevoir revient à l'analyser puis à en jeter la plus grande partie, et à se fier à la
  configuration d'un client pour ce qui atteint d'abord le serveur.
- **Le format est vaste et changeant.** Une enveloppe est une suite d'éléments typés (événements, transactions,
  sessions, pièces jointes, rapports de client), avec des en-têtes par élément et de la compression. La lire fidèlement
  est un projet en soi, et un analyseur exposé à Internet est du code à soumettre au fuzzing et à maintenir pour chaque
  type d'élément.
- **Le modèle de clé diffère.** Un DSN de Sentry nomme un projet et une clé publique ; ce bundle vérifie une origine et
  un jeton facultatif, et garde les erreurs d'une seule application dans sa propre base de données.
- **Le côté serveur n'a besoin d'aucun SDK.** Les erreurs PHP sont capturées par les écouteurs et le gestionnaire du
  bundle ; seul le navigateur avait besoin d'un client, et son contrôleur tient en quelques dizaines de lignes.

Une application qui quitte un SDK de Sentry le remplace, côté serveur, par les [points de capture](capture.md) du bundle
et, dans le navigateur, par le contrôleur Stimulus : [Migrer depuis sentry-symfony](migrating-from-sentry.md) le
parcourt pas à pas. Les points de capture sont l'interface ; un lecteur de l'enveloppe, si jamais on en a besoin, serait
un adaptateur nommé d'après ce format à côté d'eux, jamais le noyau : il transformerait chaque élément d'événement en
`ErrorEvent` par `EventFactory::fromMessage()` et le remettrait à `SafeCapture`, pour que la garde et l'assainisseur
s'exécutent toujours avant que rien ne soit écrit.
