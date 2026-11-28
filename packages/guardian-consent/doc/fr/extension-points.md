<!-- phil-dev source-sha256=a80f5b939d73ed3dd732cc08e4a13700e401cbca4fa3c5cd97cc3487a012d741 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de la version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Garder les mineurs, les demandes et les preuves dans une base de données | Implémentez `Contract\MinorStore`, `Contract\RequestStore` et `Contract\ProofStore` |
| Écrire une réponse ou un passage de relais d'un bloc | Implémentez `Contract\Transactions` sur la base de données des dépôts |
| Envoyer la confirmation et l'avis de passage de relais | Implémentez `Contract\Notifier` |
| Laisser un adulte qui gère un compte retirer un consentement | Implémentez `Contract\GuardianLink`, par exemple sur une délégation de comptes |
| Répondre aux demandes d'accès et d'effacement | Appelez `GuardianConsentRights`, qui implémente `Contract\SubjectRights`, depuis les outils de vie privée |
| Servir d'autres juridictions | Passez votre propre `Jurisdictions` : voir [Juridictions](jurisdictions.md) |
| Agir après une demande, une décision, un retrait, un effacement ou un passage de relais | Écoutez les événements : voir [Concepts](concepts.md#événements) |

## Les dépôts

`MinorStore::dueForHandover($date, $limit)` liste les mineurs qui atteignent l'âge du consentement à la date `$date` ou
avant et qui n'ont pas encore été informés, les plus anciens d'abord ; `RequestStore::expiredBy($instant, $limit)`
liste les demandes expirées à cet instant, les plus anciennes d'abord. Un dépôt en base de données les sert à partir
d'un index sur le jour de l'âge du consentement et sur l'expiration.

`RequestStore::consume($id)` retire une demande et dit si elle s'y trouvait encore : un dépôt partagé entre plusieurs
processus le fait de façon atomique — un `DELETE` dont il lit le nombre de lignes touchées —, de sorte que deux
réponses à la même demande obtiennent un seul `true`. `ProofStore::append()` ajoute seulement ; `forgetGuardian()` est
l'unique modification qu'il apporte à une preuve.

Un dépôt garde les instants en UTC, tels que les enregistrements les donnent, et les dates sous la forme `YYYY-MM-DD`.
Un dépôt qui garde un état entre les requêtes implémente `ResetInterface`, comme le font les dépôts en mémoire.

## Les transactions

`GuardianConsent` écrit la réponse d'un tuteur — la demande retirée, une preuve pour chacune de ses fonctionnalités — et
un passage de relais — les preuves du membre, les demandes abandonnées, l'enregistrement du passage — par un seul appel
à `Transactions::run($work)` chacun. Un adaptateur sur la base de données des dépôts ouvre une transaction, exécute le
travail, la valide et, sur un `Throwable`, l'annule et le relance : une écriture qui échoue laisse alors la demande, et
son lien, tels qu'ils étaient.

`NoTransactions`, la valeur par défaut, exécute le travail tel quel. Elle convient aux dépôts en mémoire et aux dépôts
qui ne sont pas dans une même base de données ; avec des dépôts en base de données, passez un adaptateur, par exemple
sur `Connection::transactional()` de Doctrine DBAL. Les événements sont répartis après le retour de `run()`.

## Le notificateur

`Notifier::sendConfirmation($request, $token)` écrit à `$request->guardian->email`, avec les mots et dans la langue de
l'application, avec un lien construit à partir de `$token->toString()` : la bibliothèque ne connaît ni les adresses du
site ni son service de courriel. Un notificateur qui met le message en file d'attente porte l'identifiant de la
demande, non le jeton ni l'adresse, et les relit à l'envoi — ou envoie aussitôt.

`Notifier::sendHandoverNotice($member)` informe le membre, à l'adresse que l'application connaît pour lui.

## Le lien de tuteur

`GuardianLink::manages($guardian, $member)` élargit qui peut retirer un consentement : un tuteur qui a décidé d'un des
consentements du membre le peut toujours. `NoGuardianLink`, la valeur par défaut, ne nomme personne.
