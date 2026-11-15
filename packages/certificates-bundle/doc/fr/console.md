<!-- phil-dev source-sha256=7e3559ac9440dfa78ad3131270d876649f7bb2920c24748d60776fd862f753c8 -->
# Commandes de console

Avec le composant Console installé, le bundle enregistre neuf commandes. Chacune passe par les mêmes services que le
code de l'application : un certificat décerné depuis la console est donc signé, stocké et annoncé comme tout autre.

| Commande | Ce qu'elle fait | Code de sortie |
|---|---|---|
| `phil:certificates:course <slug> <name> <narrative>` | Définit une formation, ou la révise avec `--revise`, comme le fait `Courses` | `0` enregistrée, `1` refusée, `2` illisible |
| `phil:certificates:award <course> <name> <identity>` | Décerne le certificat d'une formation, comme le fait `Awarding` | `0` décerné, `1` refusé, `2` illisible |
| `phil:certificates:revoke <code> <reason>` | Révoque un certificat avec sa raison, comme le fait `Revocation` | `0` révoqué, `1` refusé, `2` illisible |
| `phil:certificates:status <code>` | Montre où en est un certificat maintenant, en texte ou en JSON | `0` trouvé, `1` inconnu, `2` illisible |
| `phil:certificates:export <code>` | Écrit l'attestation signée seule | `0` écrite, `1` inconnu, `2` illisible |
| `phil:certificates:recertify <code> <identity>` | Renouvelle un certificat par un nouveau, qui y est lié, comme le fait `Awarding::recertify()` | `0` renouvelé, `1` refusé, `2` illisible |
| `phil:certificates:remind` | Envoie les rappels des certificats sur le point de prendre fin, comme le fait `ExpiryReminders` | `0` exécuté, `2` illisible |
| `phil:certificates:credit <member> <title> <credits>` | Consigne les crédits d'une activité de formation continue, ou en retire une avec `--remove` | `0` enregistré, `1` refusé, `2` illisible |
| `phil:certificates:credits [<member>]` | Montre les crédits d'un membre sur une période, ou l'avancement du titulaire d'un certificat | `0` montré, `1` inconnu, `2` illisible |

Un refus (une formation déjà définie, des critères non satisfaits, une formation retirée, un certificat déjà révoqué,
un code inconnu) et une valeur illisible (un score en lettres, un code de la mauvaise forme) vont à la sortie d'erreur,
sur une ligne qui nomme ce qui ne va pas.

## Formations

```sh
bin/console phil:certificates:course first-aid "First aid" "Score 70 % or more and practise CPR." \
    --description="The gestures that save lives." --min-score=70 --min-minutes=240 --module=cpr --validity=24 \
    --image=https://example.org/badges/first-aid.png --credits=12 --ends=2030-01-01
```

```text
Defined the course "first-aid", First aid.
https://example.org/certificates/courses/first-aid
```

Le *slug*, le nom et l'énoncé des critères sont obligatoires; l'adresse imprimée est celle de la page de la formation,
qui identifie la réalisation de ses certificats. `--min-score` est un nombre entier de 1 à 100, `--min-minutes` et
`--validity` des nombres entiers de minutes et de mois; `--module` se répète pour chaque module à terminer; une
formation sans `--validity` donne des certificats qui n'expirent pas. `--credits` est le nombre de crédits de formation
continue qu'une recertification exige, avec jusqu'à deux décimales après un point ou une virgule; `--ends` le jour où la
formation prend fin, écrit `AAAA-MM-JJ`, à minuit UTC; `--completion-source` la clé de la formation dans le système
auquel ses achèvements sont demandés ([Sources d'achèvement](completion-sources.md)). Avec `--revise`, la formation de
ce *slug* est révisée avec les valeurs données (celles qu'on omet sont effacées, comme avec `Courses::revise()`), et les
certificats déjà décernés gardent leur attestation; la source d'achèvement ne change que si `--completion-source` est
donné, et une source vide la retire. Retirer et rouvrir une formation passe par `Courses`.

## Attribution

```sh
bin/console phil:certificates:award first-aid "Ada Lovelace" ada@example.org --score=82 --minutes=240 --module=cpr --module=aed --listed --holder=member-42
```

```text
Awarded the certificate 7KQ2-M9XA-04TZ of "first-aid" to Ada Lovelace.
https://example.org/certificates/7KQ2M9XA04TZ
```

L'identité est une adresse de courriel, une URL ou un identifiant décentralisé, comme avec `Awarding::award()`; elle
n'est jamais imprimée, et l'attestation ne garde une adresse ou une URL que sous forme d'empreinte salée. `--score` est
un nombre entier de 0 à 100, `--minutes` un nombre entier de minutes, 0 si on l'omet; `--module` se répète pour chaque
module terminé; `--listed` affiche le certificat sur la page de sa formation dans le registre; `--holder` nomme le membre par l'identifiant
stable que l'application lui donne, selon lequel les crédits de formation continue sont comptés et l'achèvement d'une
formation demandé, jamais par une adresse de courriel.

Une formation qui nomme une source d'achèvement lui demande si le titulaire a achevé la formation, et l'identifiant de
l'achèvement est imprimé sur une troisième ligne :

```text
Awarded the certificate 7KQ2-M9XA-04TZ of "first-aid" to Ada Lovelace.
https://example.org/certificates/7KQ2M9XA04TZ
Completion: enrolment-7
```

`--on-completion` décerne comme le fait `Awarding::awardOnCompletion()` : il exige `--holder`, et un achèvement déjà
décerné imprime le certificat qui le porte et réussit, au lieu d'échouer; la première ligne se lit alors
`The certificate 7KQ2-M9XA-04TZ of "first-aid" is awarded to Ada Lovelace on that completion.`

## Révocation

```sh
bin/console phil:certificates:revoke 7kq2-m9xa-04tz "The assessment was taken by someone else."
```

Le code est lu comme le lit le registre : les minuscules, les tirets et les espaces sont tolérés.

## État

```sh
bin/console phil:certificates:status 7KQ2-M9XA-04TZ
```

```text
code               7KQ2-M9XA-04TZ
status             valid
course             first-aid
name               Ada Lovelace
awarded_at         2026-11-08T10:00:00+00:00
valid_until        2027-11-08T10:00:00+00:00
```

Viennent ensuite, quand ils s'appliquent, `revoked_at` et `revocation_reason`, `holder`, `completion`, `renews` et
`renewed_by` (les
codes du certificat qu'il renouvelle et de celui qui le renouvelle) et `reminded_at`. `--format=json` donne les mêmes
champs dans un seul objet JSON, `null` pour ceux qui ne s'appliquent pas : `valid_until` d'un certificat qui n'expire
pas, `revoked_at` et `revocation_reason` d'un certificat qui n'est pas révoqué. L'état est
calculé au moment du service d'horloge : un certificat au-delà de son `valid_until` se lit `expired`.

## Exporter une attestation

```sh
bin/console phil:certificates:export 7KQ2-M9XA-04TZ > credential.jwt
```

La sortie est l'attestation exactement telle qu'elle a été signée (un jeton Web JSON compact, ou le JSON d'un document
avec sa preuve), sans fin de ligne ajoutée, de sorte que le fichier est ce que lit un validateur ou un portefeuille.
Avec `-v`, le genre d'attestation et le nom de fichier qui lui convient vont à la sortie d'erreur : `credential.jwt`
(`application/vc+jwt`) ou `credential.json` (`application/vc`). [Validation](validation.md) s'en sert.

## Recertification

```sh
bin/console phil:certificates:recertify 7KQ2-M9XA-04TZ ada@example.org
bin/console phil:certificates:recertify 7KQ2-M9XA-04TZ ada@example.org --score=85
```

```text
Renewed the certificate 7KQ2-M9XA-04TZ with M3XQ-7TZA-9KQ2, valid until 2028-11-08.
https://example.org/certificates/M3XQ7TZA9KQ2
```

L'identité est celle à laquelle le certificat a été délivré; elle n'est jamais imprimée. Une formation qui exige des
crédits compte ceux du titulaire; `--score`, `--minutes` et `--module` donnent des acquis, vérifiés contre les critères,
qu'une formation sans crédits exige. [Expiration et recertification](recertification.md) dit ce qui est refusé et
pourquoi.

## Rappels

```sh
bin/console phil:certificates:remind --limit=1000
```

```text
Dispatched 3 reminders.
```

Lancez-la une fois par jour, depuis cron ou le planificateur de l'application. Elle envoie `CertificateExpiring` pour
chaque certificat qui a atteint un seuil de `reminders.days` depuis le dernier rappel de son titulaire, `--limit` au plus
par exécution, 1000 par défaut; l'exécution suivante prend le reste. L'écouteur de l'application envoie le rappel.

## Crédits de formation continue

```sh
bin/console phil:certificates:credit member-42 "Workshop on triage" 3.5 --completed=2026-11-12 --course=first-aid --source=workshop-17
bin/console phil:certificates:credit --remove=0190f1d2-3c4b-7a8e-9f00-112233445566
```

```text
Recorded 3.5 credits for member-42: 0190f1d2-3c4b-7a8e-9f00-112233445566.
```

`--completed` est le jour où l'activité a été terminée, aujourd'hui si on l'omet; `--course` la formation dont la
recertification les compte, toutes les formations si on l'omet; `--source` d'où elle vient, unique pour le membre : la
même source consignée de nouveau imprime l'activité déjà consignée.

```sh
bin/console phil:certificates:credits member-42 --from=2026-01-01 --until=2027-01-01 --course=first-aid
bin/console phil:certificates:credits --certificate=7KQ2-M9XA-04TZ
```

```text
member     member-42
from       2026-01-01
until      2027-01-01
total      7.5
  2026-11-12     3.5  Workshop on triage
  2026-06-01       4  First aid refresher
```

La période commence à `--from` et se termine avant `--until`; sans eux, ce sont les douze mois qui précèdent aujourd'hui.
Avec `--course`, seuls les crédits qui comptent pour cette formation sont additionnés et listés. Avec `--certificate`, la
commande montre les crédits que le titulaire a accumulés depuis l'attribution du certificat, ceux que sa formation exige
et ceux qui manquent encore. `--format=json` donne les mêmes champs dans un seul objet JSON.
