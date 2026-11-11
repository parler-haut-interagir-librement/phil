<!-- phil-dev source-sha256=a949256c0da50210a1ae8dbfbfa267bb4ce8fa8a747d997acebc7e3b70c44197 -->
# Enregistrer les entrées

## Codes

Un `CredentialCode` est ce qu'un appareil a lu : la charge utile d'un code scanné, ou un numéro tapé au guichet. C'est
de l'UTF-8 valide sans caractères de contrôle, de 1 à 512 octets, pris tel quel : l'appareil retire le saut de ligne
d'un lecteur. Quiconque détient un code entre : la bibliothèque le traite donc comme un secret. Aucun enregistrement ne
l'inscrit, aucun message d'exception ne le nomme, et un appareil hors ligne ne reçoit que `fingerprint($key)`, son
HMAC-SHA256 sous une clé du manifeste. Un numéro tapé au guichet ne convient qu'à l'enregistrement en ligne : un
manifeste hors ligne exige par défaut des codes de 64 bits (voir [Hors ligne](offline.md)).

## Titres d'accès

Un `Credential` est ce que l'application dit qu'un code représente :

| Partie | Sens |
|---|---|
| `id` | L'identifiant de l'application, de 1 à 128 caractères ASCII imprimables : ce que nomment les enregistrements. |
| `label` | Ce que voit le personnel — un nom, une catégorie de billet —, ou null. |
| `checkpoints` | Les `CheckpointId` qu'il ouvre ; aucun les ouvre tous. |
| `validFrom`, `validUntil` | Quand il ouvre et ferme, chacun facultatif, à la seconde. |
| `maxEntries` | Combien de fois il fait entrer son détenteur : une par défaut, null sans limite. |
| `status` | `CredentialStatus::Active` ou `Revoked` : un billet remboursé, une inscription annulée. |

```php
$pass = Credential::of('reg-4411', 'Élise Tremblay', [CheckpointId::of('hall'), CheckpointId::of('stage')], $friday, $sunday, null);
```

## La politique d'admission

`AdmissionPolicy` décide d'une lecture selon le titre d'accès, sa fréquentation, le point de contrôle, la direction et
l'instant, dans cet ordre :

1. un titre révoqué donne `Revoked` ;
2. un point de contrôle qu'il n'ouvre pas donne `WrongCheckpoint` ;
3. une sortie donne `CheckedOut` quand le détenteur est à l'intérieur, `NotInside` sinon ;
4. une entrée avant l'ouverture, moins la fenêtre anticipée, donne `NotYetValid` ;
5. une entrée après la fermeture donne `Expired` ;
6. une entrée d'un détenteur déjà à l'intérieur sous anti-retour, ou d'un titre dont les entrées sont épuisées, donne
   `AlreadyAdmitted` ;
7. toute autre entrée donne `Admitted`.

`earlySeconds` (de 0 à 86 400, aucune par défaut) fait entrer les gens avant l'ouverture, quand les portes ouvrent tôt.
`antiPassback` (actif par défaut) refuse une deuxième entrée tant que le détenteur n'est pas sorti, de sorte qu'un code
repassé par-dessus la clôture ne fait entrer personne d'autre ; désactivé, un titre sans limite entre aussi souvent
qu'il est scanné.

La décision est pure : les mêmes entrées donnent le même résultat, au guichet comme sur un appareil hors ligne, dont le
script suit le même ordre.

## Fréquentation

`Attendance` est la situation d'un titre d'accès : ses `entries`, si son détenteur est `inside`, `lastEntryAt`, et une
`version` qui compte les admissions et les sorties inscrites. Un enregistrement `Admitted` ajoute une entrée et place le
détenteur à l'intérieur ; un enregistrement `CheckedOut` le fait sortir ; un refus ne change rien.

## Le guichet

`CheckInDesk::scan()` résout le code, lit la fréquentation, décide et inscrit un `CheckIn`, refus compris : le personnel
peut voir qui a été refoulé et pourquoi. Un code inconnu n'inscrit rien.

```php
$result = $desk->scan($code, CheckpointId::of('hall'), DeviceId::of('door-1'), Direction::In, ScanId::of($fromDevice));
```

Le `ScanResult` donne le `outcome`, le `credential`, l'`attendance` une fois inscrite — `lastEntryAt` dit quand est
entré un détenteur refusé comme déjà admis —, le `checkIn` et `replayed`.

### Une lecture, une inscription

Un appareil tire un `ScanId` pour chaque lecture et l'envoie avec le code. Une lecture renvoyée sous le même identifiant
— une nouvelle tentative après une réponse perdue sur un réseau faible — n'est pas décidée de nouveau : le guichet
répond avec l'enregistrement inscrit la première fois, `replayed` étant activé. La lecture doit être la même : son code
représente le titre d'accès inscrit, au même point de contrôle, depuis le même appareil, dans la même direction. Toute
autre lecture sous un identifiant déjà inscrit — un code inconnu, un autre titre d'accès, une autre porte — reçoit
`Collision` : rien n'est inscrit, et ni le résultat ni l'enregistrement inscrits sous l'identifiant ne sont rendus,
de sorte qu'un identifiant deviné ou aperçu ne fait entrer personne et ne révèle l'entrée de personne. L'appareil
renvoie la lecture sous un nouvel identifiant. Sans identifiant, le guichet en tire un.

### Deux portes, une entrée

Une admission ou une sortie porte sa séquence, soit un de plus que la version de la fréquentation sur laquelle elle a
été décidée, et le registre ne l'inscrit que si cette version est toujours la dernière. Quand deux appareils scannent en
même temps le même billet à entrée unique, l'un inscrit en premier ; l'inscription de l'autre est refusée, et le guichet
décide de nouveau sur la nouvelle fréquentation — cette fois `AlreadyAdmitted`. Après trois refus de suite, il lève
`LedgerContention`, et la lecture peut être renvoyée sous le même identifiant.
