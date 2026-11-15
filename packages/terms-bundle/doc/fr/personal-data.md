<!-- phil-dev source-sha256=087142f7c5cf4abbc94d043cb1d51e0fc078957aed33d89c9c808dd352c6c3dd -->
# Renseignements personnels

## Ce que conserve une acceptation

| Colonne | Quoi |
|---|---|
| `member` | L'identifiant stable du membre, tel que le donne `member_resolver` ; jamais une adresse courriel avec le résolveur par défaut |
| `policy`, `version` | La version acceptée |
| `accepted_at` | Quand, selon l'horloge, gardé en UTC |
| `channel` | `signup`, `form`, `api` ou `import` |
| `network` | Quand `network` est activé, le réseau de l'adresse de la requête ; sinon rien |

L'adresse elle-même n'est jamais conservée. Quand `network` est activé, `phil/ip-anonymizer` la tronque par défaut à
un /24 pour IPv4 et à un /48 pour IPv6 ; un texte qui n'est pas une adresse ne conserve rien. Une application qui veut
un autre traitement — une empreinte avec clé, un réseau plus large — implémente `AddressMasker`.

On ne fait jamais qu'ajouter des acceptations : une acceptation plus récente s'ajoute aux précédentes, qui restent la
trace de ce qui a été accepté et quand. Le bundle n'en met jamais une à jour, et ne les retire que pour un effacement.

## Exportation et effacement

Avec `phil/privacy-bundle`, `AcceptancesPrivacy` est le module `terms` d'une demande d'accès ou d'effacement, pour le
membre que nomme la personne concernée : l'exportation énumère chaque acceptation — politique, version, moment, canal
et réseau —, l'effacement les supprime toutes et signale le stockage `phil_terms_acceptance` comme supprimé. Il
s'exécute de nouveau sur un membre déjà effacé avec le même rapport.

## Versions

Les versions contiennent les textes des politiques et rien sur les membres.
