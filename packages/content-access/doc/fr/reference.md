<!-- phil-dev source-sha256=533f4de7fdb48995815604c391b03b410354c9ae1d9595c61fd4e02cf63c16b7 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `AccessPolicy` | Ce qu'un contenu demande à son lecteur : public, règle, accès anticipé, aperçu, refus |
| `Audience` | `Public`, `Members` ou `Restricted` |
| `WhenDenied` | `Locked` ou `Hidden` : ce que voit un lecteur sans le droit |
| `AccessDecider` | Décide une politique pour un lecteur à l'instant de l'horloge |
| `Decision` | Ce que le lecteur peut voir, pourquoi, si c'est personnel, quand cela change, et l'explication |
| `Access` | `Full`, `Preview`, `Locked` ou `Hidden` |
| `Reason` | Le code stable d'une décision |
| `Viewer` | Qui demande : identifiant, rôles, domaine de courriel vérifié, adresse réseau |
| `AccessFacts` | Les faits que lit une règle d'accès, sous les noms que fixe la bibliothèque |
| `PreviewCut` | Les blocs d'un document UJO (Universal JSON Output) qu'une décision laisse passer |
| `StructuredData` | Les propriétés schema.org d'un contenu réservé |
| `Condition\AccessConditions` | Le registre des types de conditions d'accès |
| `Condition\MemberCondition` | `access.member` |
| `Condition\RoleCondition` | `access.role` |
| `Condition\FeatureCondition` | `access.feature`, avec une ouverture selon l'ancienneté et une durée glissante |
| `Condition\InstitutionCondition` | `access.institution`, par domaine de courriel vérifié ou plage de réseau |
| `Entitlement\Entitlements` | Le port qui donne les droits d'une personne |
| `Entitlement\Grant` | Une fonctionnalité détenue d'un début jusqu'à une fin facultative, et sa source |
| `Entitlement\InMemoryEntitlements` | Des droits gardés en mémoire, indexés par sujet |
| `Entitlement\ChainEntitlements` | Les droits de plusieurs sources réunis |
| `Json\AccessPolicyReader` | Lit strictement la forme JSON versionnée d'une politique |
| `Json\AccessPolicyWriter` | Écrit la forme canonique d'une politique, et son empreinte |
| `Exception\ContentAccessException` | Toute exception de la bibliothèque |
| `Exception\InvalidPolicy` | Une politique contradictoire ou mal formée |
| `Exception\InvalidViewer` | Un lecteur dont le membre est mal formé |
| `Exception\InvalidGrant` | Un droit dont la fonctionnalité ou la source est mal formée, ou dont la fin précède le début |
| `Exception\ReservedFact` | Un fait de l'application sous un nom que fixe la bibliothèque |
