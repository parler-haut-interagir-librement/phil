<!-- phil-dev source-sha256=cb29699b1e728293822e8973cbc5f300930b30b2e1c7b239144d90af6a8c4758 -->
# phil/broken-flow-alerts

**Un avertissement le jour où un parcours que vos visiteurs terminent cesse d'être terminé.**

phil/broken-flow-alerts surveille les parcours d'un site — un achat, une inscription, un formulaire de contact, tout
parcours dont l'application compte déjà la dernière étape — et prévient quand l'un est terminé bien moins souvent que
son rythme le laisse attendre. Un fournisseur de paiement qui a changé sa réponse, un bouton qui ne soumet plus, une
redirection qui tourne en boucle : les visiteurs continuent de venir, et les aboutissements s'arrêtent. La bibliothèque
s'en aperçoit d'après les comptes quotidiens, compare les derniers jours aux semaines d'avant, jour de la semaine par
jour de la semaine, et envoie une alerte le jour où le parcours s'interrompt, puis une autre le jour où il aboutit de
nouveau.

Elle est **passive** : elle lit des comptes que l'application tient de toute façon, comme les objectifs d'un outil
d'analytique, et n'envoie aucune requête au site, synthétique ou autre. Rien d'un visiteur ne l'atteint : un compte par
parcours et par jour est tout ce qu'elle lit.

La décision est un compte de Poisson. Les jours de la base fixent les aboutissements attendus de chaque jour de la
fenêtre ; pour chaque étendue qui se termine le dernier jour entier, la bibliothèque se demande à quel point si peu
d'aboutissements seraient probables dans un parcours qui fonctionne, et déclare le parcours interrompu quand la réponse
est sous le seuil de signification, 0.001 par défaut. Un parcours achalandé est interrompu après un seul jour
silencieux, un parcours terminé deux fois par jour après environ quatre, et un parcours dont le jour habituel est proche
de zéro ne l'est jamais : un jour silencieux y est ordinaire.

C'est une bibliothèque PHP simple : son seul prérequis est `psr/clock`. `NotifierAlertSender` envoie les alertes par
Symfony Notifier quand `symfony/notifier` est installé ; tout autre canal est un `AlertSender` de l'application. Avec
`phil/analytics-bundle`, les objectifs des sites sont surveillés depuis la configuration du bundle.

## État

| Partie | État |
|---|---|
| Parcours (`Flow`), leur historique (`CompletionHistory`) | Implémenté et testé |
| La règle (`BrokenFlowRule`, `FlowStatus`) : base, fenêtre, rythme par jour de la semaine, queue de Poisson à coût borné | Implémenté et testé |
| La surveillance (`BrokenFlowWatch`) : une alerte le jour où un parcours s'interrompt et le jour où il se rétablit, sans stockage | Implémenté et testé |
| Les alertes (`FlowAlert`, `FlowAlertKind`) et leur envoi (`AlertSender`, `NotifierAlertSender`) | Implémenté et testé |
| Vérifications synthétiques (requêtes envoyées au site) | Ne fait pas partie de la bibliothèque |

## Documentation

- [Installation](doc/fr/installation.md) — prérequis, installation, et Symfony Notifier, facultatif.
- [Détection](doc/fr/detection.md) — la base et la fenêtre, le rythme par jour de la semaine, le compte de Poisson, les
  réglages et ce qu'ils changent.
- [La surveillance et ses alertes](doc/fr/watch.md) — le dernier jour UTC entier, l'alerte le jour où un parcours
  s'interrompt et le jour où il se rétablit, l'historique demandé, et les notifications.
- [Points d'extension](doc/fr/extension-points.md) — `CompletionHistory` et `AlertSender`, avec des exemples
  d'adaptateurs.
- [Tests](doc/fr/testing.md) — lancer les tests, et tester un adaptateur.
- [Référence de l'API](doc/fr/reference.md) — chaque type public, avec son rôle.

## Démarrage rapide

```bash
composer require phil/broken-flow-alerts:^0.1@dev   # from a path or VCS repository until published
```

```php
use Phil\BrokenFlowAlerts\{BrokenFlowWatch, Flow};
use Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender;

$watch = new BrokenFlowWatch($history, new NotifierAlertSender($notifier), $clock);

// Once a day: the alerts of the flows that broke or recovered on the last whole UTC day
$alerts = $watch->check([Flow::of('checkout', 'Ticket sales'), Flow::of('contact', 'Contact form')]);
```

`$history` est le `CompletionHistory` de l'application : un compte par jour UTC pour un parcours, lu dans ses compteurs.

## Prérequis

- PHP 8.5 ou plus récent
- `psr/clock` 1.0
- `symfony/notifier` 8.2, facultatif, pour `NotifierAlertSender`

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
