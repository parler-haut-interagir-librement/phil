<!-- phil-dev source-sha256=9ded9316e7acba0a8c7015940697c71ff4acf82e6b8f79d296ebb3fb2247f45b -->
# Hors ligne

Une porte ne ferme pas quand le réseau tombe. Un appareil qui s'attend à le perdre télécharge d'avance un manifeste hors
ligne, décide les lectures à partir de lui pendant qu'il est hors ligne, et envoie son journal une fois de retour en
ligne.

## Manifestes

`ManifestIssuer::issue($checkpoint, $device)` liste les titres d'accès du catalogue qui ouvrent le point de contrôle,
lit leur fréquentation dans le registre par tranches de 500, et retourne un `OfflineManifest` :

| Partie | Sens |
|---|---|
| `checkpoint`, `device` | Pour qui il a été émis. |
| `issuedAt`, `expiresAt` | Quand ; valide 12 heures par défaut, de 60 secondes à une semaine. |
| `key` | 32 octets aléatoires tirés pour ce seul manifeste. |
| `policy` | La fenêtre anticipée et l'anti-retour, pour que l'appareil décide de la même façon. |
| `entries` | Un `ManifestEntry` par code : son empreinte, le titre d'accès et sa fréquentation. |

`toArray()` est ce que lit l'appareil :

```json
{
    "format": 1,
    "checkpoint": "hall",
    "device": "door-1",
    "issuedAt": 1794132000,
    "expiresAt": 1794175200,
    "key": "q0Vh3…",
    "policy": {"earlySeconds": 1800, "antiPassback": true},
    "credentials": [
        ["5d41…", "reg-4411", null, 1794128400, 1794387600, null, 1, false, false]
    ]
}
```

Chaque titre d'accès est une liste : empreinte, identifiant, libellé ou null, ouverture et fermeture en horodatages Unix
ou null, entrées maximales ou null, entrées utilisées, à l'intérieur, révoqué. La clé est en base64 adapté aux URL, sans
remplissage.

### Ce que livre un appareil perdu

Les codes ne quittent jamais le serveur. Une empreinte est le HMAC-SHA256 d'un code sous la clé du manifeste : elle
permet à l'appareil de reconnaître un code qu'il lit. La clé voyage avec les empreintes : quiconque détient un appareil
perdu peut donc calculer l'empreinte de chaque code qu'il peut deviner et la chercher. Un manifeste ne protège les codes
qu'autant qu'ils sont longs et aléatoires. Des numéros de billet à huit chiffres font 10⁸ essais, quelques minutes sur
un portable, et chaque code valide du point de contrôle est livré jusqu'à sa révocation. Chaque manifeste tire sa
propre clé : deux appareils ne détiennent donc jamais les mêmes empreintes, mais cela ne ralentit pas les essais.

L'émetteur refuse donc, par `WeakManifestCodes`, un catalogue qui contient un code de moins de 64 bits pour le point de
contrôle. `CredentialCode::estimatedBits()` donne la force d'un code : sa longueur multipliée par les bits que porte un
caractère dans l'alphabet le plus étroit qui le contient — chiffres, chiffres hexadécimaux d'une seule casse, chiffres
et lettres d'une seule casse, base64 URL-safe, ou tout caractère imprimable. C'est une borne supérieure : un code qui
suit un motif, une date ou une suite, porte moins de bits que son estimation ; les codes d'un point de contrôle hors
ligne sont donc tirés au hasard. 16 chiffres hexadécimaux aléatoires, 11 caractères de base64 URL-safe ou 20 chiffres
aléatoires atteignent 64 bits ; un code QR en porte bien plus sans effort pour le détenteur. `minCodeBits` choisit un
autre minimum, de 0, qui accepte tout code, à 128.

Les libellés — souvent le nom des détenteurs — sont laissés de côté, sauf si l'émetteur est construit avec
`labels: true`. La limite (10 000 par défaut, au plus 100 000) borne ce qu'un appareil stocke ; un catalogue qui la
dépasse lève `ManifestTooLarge`.

## Le journal

Hors ligne, l'appareil décide chaque lecture à partir de son manifeste avec la même politique, met à jour sa propre
copie de la fréquentation, et garde un `OfflineScan` dans son journal : l'identifiant de la lecture, le titre d'accès,
la direction, le résultat qu'il a décidé et l'instant selon son horloge. Un code qui ne correspond à aucune empreinte
est inconnu et n'est pas gardé.

`OfflineBatch::read()` lit le journal tel que l'appareil l'envoie, une liste JSON d'au plus 500 lectures :

```json
[{"id": "0f8fad5b-d9cb-469f-a165-70867728950e", "credential": "reg-4411", "direction": "in", "outcome": "admitted", "scannedAt": 1794132061}]
```

Une lecture mal formée fait refuser tout le lot avec `InvalidOfflineBatch`, dont le message donne sa position et jamais
les valeurs envoyées ; rien n'est inscrit.

## La synchronisation

`OfflineSync::apply($checkpoint, $device, $scans)` applique les lectures par instant, puis par identifiant, et retourne
un `SyncReport` avec un `SyncStatus` par lecture :

| État | Quand |
|---|---|
| `Recorded` | Inscrite, le registre décidant comme l'appareil. |
| `Conflict` | Inscrite avec la décision de l'appareil, le registre décidant autrement. |
| `Duplicate` | Déjà inscrite sous son identifiant : la même lecture, faite hors ligne par le même appareil au même point de contrôle, du même titre d'accès, dans la même direction, avec le même résultat. |
| `Collision` | Non inscrite : son identifiant est inscrit pour une autre lecture. |
| `Rejected` | Son titre d'accès n'est plus au catalogue, ou son instant est hors des bornes. |

Chaque lecture est décidée de nouveau par la politique selon la fréquentation du registre à son instant. Elle est
inscrite avec la décision de l'appareil, hors ligne, parce que le détenteur l'a déjà reçue à la porte : une entrée que
deux appareils hors ligne ont tous deux admise compte deux fois, et la deuxième porte `AlreadyAdmitted` comme conflit,
pour que le personnel s'y penche. Un refus que le registre aurait admis est inscrit avec `Admitted` comme conflit et ne
change rien.

La fréquentation du registre suit ce qu'affirme l'appareil. `apply()` ne vérifie ni que le titre d'accès figurait dans
un manifeste émis pour cet appareil à ce point de contrôle, ni que le manifeste était encore valide : une lecture de
tout titre d'accès que le catalogue connaît est inscrite avec le résultat qu'envoie l'appareil, et compte pour
l'anti-retour, le nombre maximal d'entrées et les chiffres de fréquentation, un conflit ne faisant que la signaler pour
révision. Quiconque peut envoyer un journal reçoit autant de confiance qu'un lecteur de porte : l'application
authentifie chaque appareil, révoque le jeton d'un appareil perdu ou trafiqué, et fait réviser les conflits par le
personnel — un conflit qu'elle peut défaire en inscrivant la décision du registre.

Un instant plus ancien que l'âge maximal (deux jours par défaut) ou postérieur à maintenant plus le décalage d'horloge
(cinq minutes par défaut) est rejeté ; un instant futur dans les limites du décalage est inscrit comme maintenant. Les
lectures faites hors ligne sont ordonnées entre elles ; une lecture faite avant une lecture en ligne déjà inscrite est
tout de même comptée après elle.

L'appareil oublie chaque lecture que nomme le rapport, quel que soit son état, et renvoie les autres plus tard.
