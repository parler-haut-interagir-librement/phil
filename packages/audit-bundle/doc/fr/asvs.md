<!-- phil-dev source-sha256=6a712efec17fa80a30b08727be2d9ec77e255411999d5d887c1672bb50787c82 -->
# Correspondance avec ASVS 5.0

Les exigences du chapitre V16 d'[OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) que le bundle
satisfait, avec le test qui montre chacune, et celles qu'il laisse à l'application. Le guide du monodépôt,
[ASVS 5.0 : ce que l'application garde](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md),
rassemble ce que chaque paquet laisse.

## Satisfaites par le bundle

| Exigence | Niveau | Comment | Démontrée par |
|---|---|---|---|
| 16.2.2 Horodatages en UTC ou avec leur décalage | 2 | Chaque instant est converti en UTC, à la microseconde, avant d'être haché et stocké, quel que soit le fuseau de l'horloge ; l'ordre du texte est l'ordre du temps | `AsvsLogIntegrityTest::testAClockInAnotherZoneWritesUtcInstantsThatVerifyAfterStorage`, `testTheEndOfDaylightSavingTimeKeepsTimeOrder` |
| 16.4.1 Données de journal encodées contre l'injection | 2 | Les faits sont des identifiants : une action, un acteur, un sujet ou un locataire contenant un retour chariot, un octet NUL, une séquence d'échappement de terminal ou tout caractère de contrôle est refusé, et une ligne stockée qui en contient un n'est pas intègre | `AsvsLogIntegrityTest::testNoFactCarriesAControlCharacter`, `testARowWhoseStoredFactsCarryAControlCharacterIsNotIntact` |
| 16.4.2 Journaux protégés contre la modification | 2 | Lignes chaînées par empreinte, têtes de segment ancrées hors de la base de données, `phil:audit:verify` | [La chaîne d'empreintes](hash-chain.md), [l'ancrage](anchoring.md) et leurs tests |
| 16.4.3 Journaux envoyés à un système distinct | 2 | Ancres écrites ailleurs ; le flux exporte les faits en JSON Lines ou en journaux OpenTelemetry | [Alimenter des tableaux de bord](dashboards.md) et ses tests |
| 16.2.5 Données sensibles journalisées selon leur niveau de protection | 2 | Champs personnels conservés sous forme d'engagements salés, effacés en oubliant leur sel | [Engagements salés et effacement](commitments-and-erasure.md) et ses tests |
| 16.2.1 Quand, qui, quoi | 2 | L'instant, l'acteur et l'action de chaque ligne, et son sujet ; le lieu est laissé à l'application | [Lire le journal](reading.md) et ses tests |
| 8.4.1 Aucune opération n'atteint un autre locataire | 2 | Un locataire lit et vérifie ses propres lignes seulement | `TenantIsolationTest` |

## Laissées à l'application

| Exigence | Niveau | Pourquoi elle revient à l'application |
|---|---|---|
| 16.1.1 Un inventaire de ce qui est journalisé, où et pour combien de temps | 2 | La documentation de l'application, qui liste les segments qu'elle enregistre |
| 16.2.1 Où un événement s'est produit | 2 | L'application ajoute son hôte, sa route ou son processus à la charge utile |
| 16.3.1 à 16.3.4 Authentification, échecs d'autorisation, tentatives de contournement, erreurs inattendues | 2 | Les événements à enregistrer sont ceux de l'application ; le bundle enregistre ce qu'on lui donne, et son intégration de Workflow enregistre les changements d'état |
| 16.4.2 pour le rôle de la base de données | 2 | Le rôle avec lequel l'application se connecte ne doit détenir ni `UPDATE` ni `DELETE` sur la table d'audit |
