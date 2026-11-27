<!-- phil-dev source-sha256=3c4248d6fc233eef6c895e8f7a07418ae22d1ebdf976ea06d6b9c0be1cdbda46 -->
# ASVS 5.0 : ce qui reste à l'application

[English](ASVS.md)

Les paquets de ce monodépôt satisfont certaines exigences de l'[OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en)
et montrent chacune par un test ; chaque paquet qui le fait a une page `doc/asvs.md`. Certaines exigences ne peuvent
pas être satisfaites par un paquet : elles dépendent de ce que l'application consigne, de ses comptes, des rôles de sa
base de données ou de ses hôtes. Ce guide les rassemble, pour qu'une application bâtie sur les paquets sache ce qui
lui revient encore.

| Paquet | Sa correspondance |
|---|---|
| `phil/browser-security-bundle` | [doc/fr/asvs.md](packages/browser-security-bundle/doc/fr/asvs.md), chapitre V3 |
| `phil/audit-bundle` | [doc/fr/asvs.md](packages/audit-bundle/doc/fr/asvs.md), chapitre V16 |
| `phil/upload-scan` | [doc/fr/asvs.md](packages/upload-scan/doc/fr/asvs.md), chapitre V5 |
| `phil/upload-scan-bundle` | [doc/fr/asvs.md](packages/upload-scan-bundle/doc/fr/asvs.md), chapitre V5 |
| `phil/passwords` | [doc/fr/asvs.md](packages/passwords/doc/fr/asvs.md), chapitre V6 |
| `phil/tenancy-bundle` | [plus bas](#locataires), chapitre V8 |

## La documentation que l'application écrit

| Exigence | Niveau | Ce qu'il faut écrire |
|---|---|---|
| 8.1.1 | 1 | Les règles d'accès aux fonctions et aux données : quel rôle ou quel *voter* garde chaque route et chaque sorte d'enregistrement. `phil/route-access-check` liste les routes et leurs règles. |
| 8.1.2 | 2 | Les règles d'accès aux champs, en lecture et en écriture : quels champs un formulaire ou une API montre et accepte pour chaque rôle. |
| 16.1.1 | 2 | L'inventaire de ce qui est journalisé à chaque couche, où c'est stocké, pour combien de temps et qui le lit : les segments de la piste d'audit, les canaux de journalisation de l'application, le traqueur d'erreurs. |
| 3.1.1 | 3 | Les fonctionnalités du navigateur que l'application attend : HTTPS, HSTS, la politique de sécurité du contenu. |
| 5.1.1 | 2 | Les types et les tailles de fichiers que chaque téléversement accepte. |

## Les événements que l'application consigne

La piste d'audit consigne ce qu'on lui donne. Ces événements reviennent à l'application :

| Exigence | Niveau | Événements |
|---|---|---|
| 16.3.1 | 2 | Chaque connexion, réussie ou non, avec la sorte d'authentification. Les `LoginSuccessEvent` et `LoginFailureEvent` de Symfony sont là où écouter. |
| 16.3.2 | 2 | Chaque autorisation refusée ; au niveau 3, chaque décision sur des données sensibles. L'`AccessDeniedException` de Symfony et les *voters* sont là où écouter. |
| 16.3.3 | 2 | Les tentatives de contourner un contrôle : une validation refusée exprès, une limite de débit atteinte (`RateLimitExceeded`), un jeton forgé. |
| 16.3.4 | 2 | Les erreurs inattendues et les échecs d'un contrôle, comme un échec TLS vers un service en arrière-plan. |
| 16.2.1 | 2 | Où chaque événement s'est produit : l'hôte, la route ou le processus, dans le contenu de la rangée. |

## La configuration et le déploiement

| Exigence | Niveau | Ce qu'il faut régler |
|---|---|---|
| 13.2.2 | 2 | Un rôle de base de données au moindre privilège : aucun `BYPASSRLS` quand la sécurité au niveau des rangées garde les locataires, ni `UPDATE` ni `DELETE` sur la table d'audit. |
| 8.4.1 | 2 | `framework.trusted_hosts`, dès que les hôtes choisissent le locataire : sans lui, un en-tête `Host` forgé le choisit. |
| 3.3.1 à 3.3.4 | 1, 2 | Des témoins de session `Secure`, `HttpOnly` et `SameSite`, et le préfixe `__Host-` là où le témoin n'est pas partagé. |
| 3.4.2 | 1 | Un `Access-Control-Allow-Origin` fixe, ou tiré d'une liste d'autorisation. |
| 5.2.3 | 2 | ClamAV 1.5.4 ou plus récent, avec `AlertExceedsMax yes` ([clamd](packages/upload-scan/doc/fr/clamd.md#les-archives-et-leurs-limites)). |
| 6.2.1 | 1 | Une contrainte `Length` d'au moins 8 caractères, 15 recommandés, à côté de `MinimumStrength`. |
| 6.2.4 | 1 | `NotCompromised` à l'inscription et au changement de mot de passe : le dictionnaire hors ligne de `phil/passwords` contient 314 mots de passe, la source des fuites les 3 000 plus courants et davantage. |

## Les arguments associés et les votes

Depuis Symfony 8.2, un argument associé à la requête par `#[MapRequestPayload]`, `#[MapQueryString]` ou
`#[MapUploadedFile]`, quand il est le sujet d'un `#[IsGranted]`, est lu et validé avant le vote : un visiteur que le
vote refuserait lit les erreurs de validation, et exécute les contraintes, une analyse antivirus comprise. Écrivez un
`#[IsGranted('IS_AUTHENTICATED')]`, ou un rôle, avant un tel vote, ou gardez le chemin dans `access_control`.
`phil/route-access-check` signale comme conflit une route qui ne le fait pas.

## Locataires

`phil/tenancy-bundle` satisfait ces exigences, chacune montrée par ses tests :

| Exigence | Niveau | Comment | Montré par |
|---|---|---|---|
| 8.4.1 Aucune opération n'atteint un autre locataire | 2 | Lectures : le filtre Doctrine, fermé en cas d'échec ; la table d'identité détachée quand le locataire change ; le cache, Messenger et les fournisseurs d'utilisateurs limités au locataire. Écritures : la garde d'écriture refuse un *flush* de l'entité d'un autre locataire | `IdentityMapTest`, `DoctrineFilterTest`, `CacheTest`, `MessengerTest`, `MembersTest` |
| 8.2.2 L'accès aux données restreint aux permissions explicites | 1 | Dans un locataire : un compte n'atteint que les locataires qu'il peut atteindre | `ActiveTenantRequestTest`, `SitesTest` |
| 8.3.1 Les règles appliquées sur le serveur | 1 | Le locataire est déterminé par le serveur à partir de l'hôte, de la session ou de l'estampille, jamais d'un champ que le client fixe | `ActiveTenantRequestTest` |
| 13.2.2 Le moindre privilège envers la base de données | 2 | La sécurité au niveau des rangées facultative sur PostgreSQL, sous l'ORM | `PostgreSqlRowLevelSecurityTest` |

Ce qui reste à l'application : les écritures par DBAL ou en SQL natif, que ni le filtre ni la garde d'écriture ne
voient — la sécurité au niveau des rangées les couvre — ; la référence au locataire de chaque nouvelle entité, fixée à
partir du locataire déterminé ; `trusted_hosts` ; et un *flush* avant de changer de locataire, puisqu'un changement à
une entité chargée n'est pas planifié avant lui.
