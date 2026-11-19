<!-- phil-dev source-sha256=a85736e299edc1dadbce520f02f8e61e3e38b099116acbc97e2a4516f8813526 -->
# Exceptions

Chaque exception implémente `Phil\UsageMeter\Exception\UsageMeterException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `QuotaExceeded` | `RuntimeException` | `consume()` ferait dépasser sa limite au sujet ; rien n'a été compté |
| `UnknownMeter` | `InvalidArgumentException` | un appel nomme un compteur que `Meters` ne contient pas |
| `InvalidUsage` | `InvalidArgumentException` | un nom de compteur, une limite, une période, un sujet, une quantité, une clé ou un nom de table ne peut pas être utilisé, ou la plateforme de base de données n'est pas prise en charge |

`QuotaExceeded` porte l'`Usage` au moment du refus (utilisé, limite, début, `resetsAt`) et la quantité demandée :
l'application peut ainsi dire combien il reste et quand il y en aura plus. Son message nomme le compteur, jamais le
sujet :

```text
The quota of "exports" is reached: 3 used of 3, 1 more requested; the window changes at 2026-04-01T00:00:00+00:00.
```

Les autres messages nomment ce qui ne va pas :

```text
No meter is named "imports".
A subject is named by 1 to 255 bytes.
An amount counted is one or more; got 0.
An idempotency key is 1 to 255 bytes.
A limit is zero or more; got -1.
"Exports" is not a meter name: up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter or a digit.
The meter "exports" is declared twice.
"Mars/Olympus" is not a time zone.
A billing period lasts 1 to 120 months; got 0.
A rolling window lasts one second or more and is cut into 1 to 1440 buckets that divide it exactly; got 100 seconds in 7 buckets.
DbalUsageStore runs on PostgreSQL, MySQL, MariaDB and SQLite; not on Doctrine\DBAL\Platforms\SQLServerPlatform.
```

Le stockage en base de données laisse passer les exceptions propres à la connexion après avoir annulé sa transaction.
