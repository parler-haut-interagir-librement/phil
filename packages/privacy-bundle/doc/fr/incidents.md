<!-- phil-dev source-sha256=dc8a915e320fe61998a048e789c4edef8bba6978200fcc6e78d1c3a4c209251a -->
# Le registre des incidents de confidentialité

La loi demande à une entreprise de tenir un registre de **chaque** incident de confidentialité — un renseignement
personnel consulté, utilisé, communiqué ou perdu sans autorisation —, grave ou non, et d'aviser l'autorité et les
personnes concernées de celui qui présente un risque de préjudice sérieux. Le bundle tient ce registre.

Au Québec, la Commission d'accès à l'information précise ce que contient le registre, comment juger si un incident
présente un risque de préjudice sérieux, et ce que disent les avis, dans ses lignes directrices du 30 janvier 2026 sur
les incidents de confidentialité ; lisez-les avec la personne responsable de la protection des renseignements
personnels. Le RGPD demande le même registre des violations (article 33(5)) et un avis à l'autorité dans les 72 heures.
Voir [legal-framework.md](legal-framework.md).

```bash
bin/console phil:privacy:incident "Mailing list exported to a public folder" \
    --occurred="2026-11-09 08:00" --category=email --category=person_name --affected=120 --serious-risk
# Incident 4f3c… recorded.
# It presents a risk of serious injury: notify the authority promptly, then record it with --authority-notified.
# It presents a risk of serious injury: notify the people concerned promptly, then record it with --subjects-notified.

bin/console phil:privacy:incident --id=4f3c… --measures="Folder removed, link revoked" --authority-notified="2026-11-10 15:00"
bin/console phil:privacy:incident --id=4f3c… --subjects-notified=2026-11-11 --closed=2026-11-20

bin/console phil:privacy:incidents --format=markdown > incidents.md
```

Une ligne contient :

| Champ | Option | Règle |
|---|---|---|
| résumé | l'argument | Ce qui s'est passé ; obligatoire pour un nouvel incident, au plus 4 000 caractères. |
| découverte | `--discovered` | Quand il a été découvert ; maintenant par défaut. |
| survenue | `--occurred` | Quand il s'est produit, si on le sait ; jamais après la découverte. |
| catégories | `--category`, répétée | Les sortes de renseignements personnels concernés, comme jetons `DataCategory`. |
| personnes touchées | `--affected` | Combien de personnes, si on le sait. |
| risque sérieux | `--serious-risk`, `--no-serious-risk` | S'il présente un risque de préjudice sérieux. |
| mesures | `--measures` | Ce qui a été fait pour réduire le risque et en éviter un autre, au plus 4 000 caractères. |
| autorité avisée | `--authority-notified` | Quand l'autorité a été avisée. |
| personnes avisées | `--subjects-notified` | Quand les personnes concernées ont été avisées. |
| fermeture | `--closed` | Quand il a été fermé. |

Aucune date ne peut précéder la découverte. Avec `--id`, la commande complète une ligne : une valeur omise garde celle
qu'elle avait. Après chaque inscription, elle nomme les avis qu'un risque de préjudice sérieux demande encore
(`Incident::pendingNotifications()`). Juger si un risque est sérieux, et les avis eux-mêmes, reviennent à la personne
responsable de la vie privée.

`phil:privacy:incidents` affiche le registre, l'incident découvert le plus récemment en premier, en tableau Markdown à
remettre à l'autorité qui le demande, ou en JSON avec `pending_notifications` pour chaque ligne.

## Le garder

Le registre est la table `phil_privacy_incident`, ajoutée au schéma que génère l'ORM ; un autre stockage est un
`IncidentRegister` nommé sous `phil_privacy.incidents.store`. Une ligne n'est jamais retirée à la main. Gardez-le au
moins aussi longtemps que la loi le demande — cinq ans à compter de la découverte au Québec — avec une règle de
conservation sur son stockage, qui retire les incidents fermés découverts avant la date limite, jamais un incident
ouvert :

```yaml
phil_privacy:
    retention:
        rules:
            R-INC: { store: phil_privacy_incident, period: P5Y, purpose: The register of confidentiality incidents, location: Canada }
```
