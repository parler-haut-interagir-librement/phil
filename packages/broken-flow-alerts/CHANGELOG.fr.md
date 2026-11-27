<!-- phil-dev source-sha256=f41bd7c0d953b655f41369445d21316daf7ee10cde039ac26464810c30f19667 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/broken-flow-alerts` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Flow` : un identifiant que comprend l'historique de l'application et un libellé, chacun de 1 à 128 octets sans
  espaces autour ni caractères de contrôle.
- `CompletionHistory`, le port de l'application : un compte par jour UTC pour un parcours, du plus ancien au plus
  récent.
- `BrokenFlowRule` et `FlowStatus` : une référence de 7 à 366 jours (28 par défaut) fixe le rythme de chaque jour d'une
  fenêtre de 1 à 31 jours (7 par défaut), jour de la semaine par jour de la semaine dès deux semaines de référence ;
  chaque tronçon de la fenêtre qui finit à son dernier jour est pesé comme un compte de Poisson, et le parcours est
  brisé quand le plus calme est moins probable que le seuil de signification (0,001 par défaut). La queue est sommée en
  logarithmes jusqu'à 10 000 achèvements, et bornée au-dessus par Chernoff.
- `BrokenFlowWatch` : les parcours vérifiés le dernier jour UTC entier, la veille évaluée de nouveau à partir du même
  historique pour qu'une alerte parte le jour où un parcours se brise et le jour où il s'achève de nouveau, sans
  stockage des alertes passées ; un silence devenu le rythme n'est pas un rétablissement.
- `FlowAlert` et `FlowAlertKind` (`Broken`, `Recovered`) : le parcours, les jours, les achèvements observés et attendus.
- `AlertSender`, et `Notifier\NotifierAlertSender`, une notification de Symfony Notifier d'importance élevée pour un
  parcours brisé et d'importance faible pour un rétablissement, aux destinataires qu'on lui donne ou aux canaux qui n'en
  demandent aucun.
- `BrokenFlowAlertsException`, implémentée par `InvalidFlowValue`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README, et son miroir français dans `README.fr.md`
  et `doc/fr/`.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
