<!-- phil-dev source-sha256=f14f1c74eb0db566054e9d5c68c033f86f0c9c8202c56b2c25dc4ca8f89148c1 -->
# La chaîne d'empreintes

**État : implémentée, sauf l'opération d'effacement.** Cette page énonce la conception et, section par section, ce que
le bundle en fait.

## Ce qu'elle est, et ce qu'elle n'est pas

Une piste d'audit **en ajout seul**, chaînée par empreintes, qui reste compatible avec l'effacement des renseignements
personnels.

C'est un journal, pas une source de vérité : ce n'est **pas une source d'événements**, et aucun état n'est reconstruit
à partir d'elle.

## Modèle de menace

La piste est écrite contre **un administrateur qui a accès à la base de données**, la personne contre laquelle une
piste d'audit doit tenir.

- Le chaînage rend détectable la modification d'une ligne : l'empreinte de chaque ligne suivante en dépend.
- Le chaînage seul peut être défait par quiconque est capable de réécrire **toute** la chaîne à partir de la ligne
  modifiée. Seule une ancre écrite hors de la base de données, vers un stockage que cet administrateur ne peut pas
  réécrire, rend cette réécriture détectable ([anchoring.md](anchoring.md)).
- Sans ancres, une réécriture complète de la chaîne passe inaperçue. C'est la première chose à savoir sur le bundle.

## La conception

### Des lignes chaînées par empreintes

Chaque ligne porte l'empreinte de sa charge utile et l'empreinte de la ligne précédente. L'empreinte de la ligne est
calculée sur la **sérialisation canonique** de ses champs ([serialization-door.md](serialization-door.md)) : les champs
non personnels et les engagements des champs personnels, **jamais** leur texte en clair
([commitments-and-erasure.md](commitments-and-erasure.md)).

Ce qui existe : `AuditRow`. Son empreinte est le SHA-256, en hexadécimal, de son segment, de sa séquence, de son genre,
de son instant (UTC, à la microseconde), de sa version de sérialisation, de l'empreinte de la ligne précédente, de ses cinq
faits (action, acteur, type du sujet, identifiant du sujet et locataire, une ligne vide pour chacun qui manque
([reading.md](reading.md))) et de la charge utile, chacun sur sa propre ligne et la charge utile en dernier, pour
qu'aucun champ ne déborde sur le suivant. Aucun des champs qui précèdent la charge utile ne peut contenir de saut de
ligne : une ligne dont le segment, la version ou les faits en contiennent un, ou dont les faits ne sont pas des
identifiants, n'est pas intègre, quelle que soit son empreinte, car un saut de ligne pourrait déplacer une valeur
dans la ligne suivante en gardant l'empreinte. La première ligne d'un segment est liée à `AuditRow::GENESIS`, 64 zéros.
`AuditTrail::record($segment, $fields, $facts)` sérialise les champs avec le `CanonicalSerializer` de l'application,
estampille les faits du locataire courant quand ils n'en nomment aucun, lie la ligne à la tête de son segment et
l'ajoute. Une ligne qu'un autre processus a ajoutée en premier (`ChainConflict`, la clé primaire
`(segment, sequence)` la refusant) est retentée sur la nouvelle tête, trois fois en tout. La table garde l'instant sous forme de texte : un type
date l'arrondirait et briserait l'empreinte.

### La version de sérialisation dans chaque ligne

Chaque ligne consigne la version de la sérialisation sous laquelle son empreinte a été calculée
(`CanonicalSerializer::version()`), et l'empreinte de la ligne couvre cette version. Une sérialisation ultérieure est
une nouvelle version à côté de l'ancienne, jamais un remplacement.

Ce qui existe : la `version` de chaque ligne, tirée de `CanonicalSerializer::version()`, incluse dans l'empreinte de la
ligne. La vérification calcule l'empreinte de la charge utile **telle qu'elle est stockée** : elle ne lit pas la
version et ne sérialise pas les champs de nouveau. Une ligne écrite sous n'importe quelle version reste donc
vérifiable sans son sérialiseur.

### L'effacement garde l'empreinte

L'effacement supprime le texte en clair d'un champ personnel et son sel, et garde l'engagement. Comme l'empreinte
couvre l'engagement, l'empreinte de la ligne ne change pas, chaque ancre reste vérifiable, et la charge utile se lit
légitimement `[erased]`.

Ce qui existe : `Commitment` et `Salt`, qui rendent cela possible. L'opération d'effacement elle-même relève pour
l'instant de l'application.

### Une ligne de discontinuité après chaque restauration

Une restauration à un point dans le temps ramène la table en deçà de lignes auxquelles des ancres se sont déjà
engagées. Après chaque restauration, une **ligne de discontinuité** consigne la restauration et est ancrée
immédiatement : l'écart entre la dernière ancre et la tête restaurée est ainsi expliqué plutôt que signalé comme une
falsification. Une ligne de discontinuité n'est qu'une explication : elle ne scinde pas le segment, et la vérification
des lignes qui la précèdent et la suivent est la même.

Ce qui existe : `AuditTrail::recordDiscontinuity($segment, $reason)`, une ligne de genre `discontinuity`, à ancrer
sur-le-champ (`phil:audit:anchor`).

### La vérification par segment

La vérification s'exécute par **segment**, le `segment` des lignes. Elle vérifie chaque ligne du segment et ses
empreintes de bornes par rapport à ses ancres. Une rupture inexpliquée reste exactement ce qu'elle doit être : une
alarme. La vérification est faite pour échouer bruyamment.

Ce qui existe : `ChainVerifier`, exécuté par `TrailVerification` et `phil:audit:verify`. Pour chaque segment, il
recalcule l'empreinte de chaque ligne (`the row was changed after it was written`), vérifie chaque lien (`the row does
not link to the row before it`) et la séquence (`row N is missing`), et vérifie chaque ancre relue par `AnchorReader` :
sa première empreinte doit être celle du début de la chaîne (`the anchor of … does not start where the chain starts`),
et son empreinte de tête doit être celle d'une ligne (`no row has the head hash anchored at …`). Dans le segment d'un
site, chaque ligne doit porter le locataire de ce site (`the row is not of the site the segment belongs to`,
[sites.md](sites.md)).

Une tête perdue n'est **expliquée** que par une ligne de discontinuité consignée après la période de l'ancre perdue et
au plus tard à l'ancre suivante dont la chaîne a encore la tête, quand cette ancre suivante la couvre : sa tête est la
ligne de discontinuité ou une ligne qui la suit. La discontinuité a alors été ancrée, et elle existait donc avant
l'écriture de cette ancre. Une rupture expliquée est tout de même affichée, avec la séquence de la discontinuité et
`explained by this discontinuity`, et le segment `holds, with N explained break(s) to review`. Une réécriture suivie
d'une discontinuité forgée ressemble en effet à une restauration, et seule une personne qui la compare aux
restaurations réellement effectuées peut les distinguer. `phil:audit:verify` échoue donc aussi sur une rupture
expliquée, et indique combien il en reste à examiner; une fois qu'une personne les a comparées aux restaurations,
`--accept-explained` le laisse réussir quand chaque rupture est expliquée. Une rupture qu'aucune discontinuité
n'explique échoue avec ou sans l'option. Dès qu'un segment a des ancres, une discontinuité qu'aucune
ancre ne couvre est une alarme (`the discontinuity is covered by no anchor`) : ancrez juste après en avoir consigné une.

Les lignes sont lues une seule fois, dans l'ordre. La vérification garde la première empreinte, la séquence à laquelle
chaque tête ancrée a été trouvée et les lignes de discontinuité : sa mémoire croît donc avec les ancres et les
restaurations, pas avec les lignes. La commande affiche chaque rupture et se termine en échec à la moindre alarme :
surveillez son code de sortie.

## Pas commencé

L'effacement comme opération du bundle : texte en clair et sel supprimés, engagement gardé, champ de motif en texte libre
caviardé en entier.

## Ce qui relève de l'application

- le stockage des ancres, sous verrouillage d'objets en mode conformité ou l'équivalent;
- le partitionnement des tables et les durées de conservation;
- les rôles de base de données qui peuvent écrire et supprimer;
- la liste des événements audités et la liste des champs déclarés personnels;
- l'alerte en cas d'échec de la vérification;
- la sérialisation canonique, jusqu'à ce que le bundle fournisse sa propre version figée.
