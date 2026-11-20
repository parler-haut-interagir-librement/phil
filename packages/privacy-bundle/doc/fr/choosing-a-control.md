<!-- phil-dev source-sha256=2708f8c4bfab44bcafe5cdfae8693567471ed1eab19b28135fab9885512a93de -->
# Choisir un contrôle

Supprimer, conserver pour une durée, anonymiser, chiffrer et pseudonymiser sont des outils différents. Chacun répond à
un problème et en laisse d'autres ouverts ; aucun n'est un interrupteur de vie privée. Cette page dit quel outil du
bundle répond à quel problème, et où il s'arrête.

## L'échelle pour laquelle ce bundle est fait

Le bundle est fait pour le **palier 1** : une application, une base de données relationnelle, ses sauvegardes, ses
journaux, ses *workers* et quelques services externes qu'elle appelle. À cette échelle, les données de la personne
vivent dans des tables que possède l'application, et les effacer revient à modifier ces tables et les copies dérivées
que l'application garde, et à demander la même chose aux services externes.

Deux paliers plus grands existent : plusieurs services qui possèdent chacun leurs données, puis une plateforme de
données où des copies alimentent l'analytique. Ils ajoutent des flux, des magasins d'événements et des entrepôts qui
gardent des données hors de portée d'un `UPDATE`. Le bundle ne les couvre pas ; une application qui y accède ajoute ses
propres contrôles, et garde ceux qui suivent pour ses propres tables.

## La grille

| Problème | Outil du bundle | Limite |
|---|---|---|
| Une personne demande à être effacée | Un `PersonalDataEraser` par module, exécuté par `SubjectErasure` ou par le [workflow d'effacement](erasure-workflow.md) | Efface ce que connaissent les effaceurs. Une table, un cache, un journal ou une copie qu'aucun effaceur ne couvre garde les données. |
| Un module efface par un service externe | `ExternalPersonalDataEraser`, dont le service accuse réception | La parole du service fait foi : le bundle ne peut pas vérifier ce que le service a gardé. |
| Des enregistrements survivent à leur finalité | Le calendrier de conservation et `RetentionRun`, stockage par stockage ([conservation](retention.md)) | Un stockage sans règle n'est jamais purgé ; le calendrier ne purge que ce dont répond un purgeur. |
| Un enregistrement doit rester, mais pas la personne qu'il contient | Un effaceur qui caviarde ou pseudonymise sur place, rapporté comme `Redacted` ou `Pseudonymised` | L'enregistrement reste un renseignement personnel : les autres champs, un pseudonyme et son lien de retour désignent encore la personne. |
| Un enregistrement doit rester, et rien dedans ne doit désigner la personne | Un effaceur qui le réduit à un squelette, rapporté comme `Anonymised` | Seulement quand quelqu'un a jugé et consigné par écrit que rien de ce qui reste ne distingue la personne, seul ou avec ce que le site ou quiconque peut raisonnablement utiliser. Une valeur rare, un champ de texte libre ou une date de naissance le font souvent. |
| Une copie de la base pour le développement ou le soutien | `phil:privacy:anonymize` ([anonymisation](anonymization.md)) | Masque les colonnes qu'on lui indique ; une colonne qu'on ne lui indique pas est copiée telle quelle. |
| Une copie volée de la base | Les colonnes chiffrées, `#[Encrypted]` ([colonnes chiffrées](encrypted-columns.md)) | Protège les colonnes au repos, pas l'application : quiconque exécute l'application, ou atteint ses clés, les lit. |
| Retrouver une ligne par une valeur chiffrée | Un index aveugle, `#[BlindIndexed]` | Des valeurs égales donnent des index égaux : quiconque lit la table voit quelles lignes partagent une valeur, et un petit domaine, comme un code postal, peut être essayé en entier. |
| Des journaux qui répètent ce que portent les requêtes | Le caviardage des journaux ([caviardage des journaux](log-redaction.md)) | Caviarde les clés et les motifs qu'on lui donne ; une valeur à l'intérieur d'un message de texte libre passe. |
| Des événements et des messages qui survivent aux lignes | Des charges utiles de phil/outbox faites d'identifiants, des messages faits d'identifiants | Tient tant que chaque paquet s'y conforme ; une charge utile qui porte une valeur la garde jusqu'à ce que l'outbox purge l'enregistrement. |

## Pseudonymisé n'est pas anonymisé

Un pseudonyme, un jeton, une empreinte à clé ou un identifiant interne identifie encore la personne pour qui détient le
lien de retour : la clé, le sel ou la table qui le fait correspondre. Des données pseudonymisées restent des
renseignements personnels. Rapportez un stockage comme `Pseudonymised`, et gardez `Anonymised` pour un squelette jugé non
identifiable. Le registre et les rapports marquent la différence : chaque effet sauf `Deleted` et `Anonymised` indique
qu'il reste des renseignements personnels (`personal_data_left`).

## Écartés

- **Un coffre de jetons.** Remplacer chaque valeur personnelle par un jeton gardé dans un coffre à part déplace les
  données vers le coffre, où un effacement doit encore les atteindre, et place le coffre sur le chemin de chaque
  lecture. Au palier 1, les propres tables de l'application, effacées sur place, sont plus simples et effacent autant.
- **Le chiffrement transparent de la base comme effacement.** Le chiffrement des fichiers de la base par le serveur de
  base de données (TDE) protège les disques et les sauvegardes contre le vol. Le serveur déchiffre chaque ligne pour
  quiconque se connecte : il n'efface rien et ne limite pas ce que l'application lit.
- **Détruire une clé comme effacement.** Détruire la clé de colonnes chiffrées révoque l'accès à ce qu'elle scellait ;
  que cela compte comme effacer dépend de qui d'autre détenait la clé et de la loi applicable, et les autres copies —
  sauvegardes, journaux, exports — ne sont pas touchées.

## Les sauvegardes

Une sauvegarde prise avant un effacement contient les données de la personne jusqu'à son expiration. Gardez les
sauvegardes pour une courte durée écrite ; vérifiez qu'elles expirent comme écrit ; et après une restauration, effacez de
nouveau les personnes dont l'effacement est antérieur à la copie restaurée, à partir des demandes d'effacement et des
suppressions de compte que garde le bundle. Un effacement est idempotent : le relancer sur une personne déjà effacée ne
fait rien de plus.
