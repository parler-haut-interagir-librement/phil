<!-- phil-dev source-sha256=42c2e7af5d0d633cb94940a45d710fb487e25621ad002a63e9c617c0a1b552f4 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de la version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Garder les mineurs, les demandes et les preuves dans une base de données | Implémentez `Contract\MinorStore`, `Contract\RequestStore` et `Contract\ProofStore` |
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

## Le notificateur

`Notifier::sendConfirmation($request, $token)` écrit à `$request->guardian->email`, avec les mots et dans la langue de
l'application, avec un lien construit à partir de `$token->toString()` : la bibliothèque ne connaît ni les adresses du
site ni son service de courriel. Un notificateur qui met le message en file d'attente porte l'identifiant de la
demande, non le jeton ni l'adresse, et les relit à l'envoi — ou envoie aussitôt.

`Notifier::sendHandoverNotice($member)` informe le membre, à l'adresse que l'application connaît pour lui.

## Le lien de tuteur

`GuardianLink::manages($guardian, $member)` élargit qui peut retirer un consentement : un tuteur qui a décidé d'un des
consentements du membre le peut toujours. `NoGuardianLink`, la valeur par défaut, ne nomme personne.
