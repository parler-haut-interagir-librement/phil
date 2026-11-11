<!-- phil-dev source-sha256=2dfb873dda6487ec4b0950fe9019427031d8bc49d902c1f61843dcc8d8448352 -->
# phil/bot-detection-bundle

**Distinguez un robot d'un humain dans le navigateur, et ne laissez sortir que ce seul mot.**

phil/bot-detection-bundle produit une seule sortie : un verdict robot ou humain. Dans le navigateur, un contrôleur
Stimulus exécute localement des détecteurs d'automatisation (entre autres WebDriver, Chrome sans interface, les
variables globales et les cadres de pile d'automatisation, un moteur de rendu logiciel) et ne laisse sortir que `human`
ou `robot`. Sur le serveur, `Verdict` lit strictement ce que le navigateur soumet. Une `FrictionPolicy` choisie par
surface le traduit en friction, et les verdicts robot vont à un compteur agrégé qui ne prend aucun argument.

Il s'adresse aux applications Symfony qui veulent ralentir les visiteurs automatisés sans nuire aux personnes. Aucun
identifiant ni aucune empreinte de navigateur n'est calculé, envoyé ou conservé, le verdict lui-même n'est jamais
stocké, et aucun tiers n'est jamais appelé. Un verdict ne refuse jamais une requête : la friction signifie une étape de
plus. Distinguer les bons robots d'indexation des mauvais revient à une liste de robots côté serveur.

D'autres paquets s'appuient sur le verdict. `phil/analytics-bundle` le lit pour garder les robots hors de ses compteurs,
et `phil/spam-protection-bundle` rend son inspection d'un formulaire sous forme de verdict.

Le bundle est un squelette du verdict, de la politique de friction et de `VerdictHandler`, avec un port pour ce que
l'application décide : l'endroit où `RobotVerdictCounter` garde son compte. `PhilBotDetectionBundle` ne déclare aucun
service et aucune configuration.

## État

| Domaine | État |
|---|---|
| `Domain\Verdict\Verdict` | Implémenté et testé |
| `Domain\Friction\Friction`, `Domain\Friction\FrictionPolicy` | Implémenté et testé |
| `Application\VerdictHandler` | Implémenté et testé |
| `Domain\Counter\RobotVerdictCounter` | Port, implémenté par l'application |
| Détecteurs du navigateur : le contrôleur Stimulus `verdict` | Implémentés, testés avec Node ; aucun code tiers inclus |
| `PhilBotDetectionBundle` | Aucun service, aucune configuration |

## Documentation

- [Installation](doc/fr/installation.md) — les prérequis, l'installation depuis un dépôt tant que le bundle n'est pas
  sur Packagist, et son enregistrement (qui ne change encore rien).
- [Configuration](doc/fr/configuration.md) — le bundle n'a aucune clé de configuration et ne déclare aucun service ;
  comment brancher une politique, un compteur et un gestionnaire par surface, et les décisions qui reviennent à
  l'application.
- [Le verdict](doc/fr/verdict.md) — les valeurs transmises `human` et `robot`, l'analyse stricte qui ne lève jamais
  d'exception, et pourquoi un verdict n'est ni un identifiant ni stocké.
- [La friction](doc/fr/friction.md) — une étape de plus et jamais un refus, les deux politiques pour un verdict absent,
  et le choix entre elles par surface.
- [Traiter un verdict](doc/fr/verdict-handling.md) — `VerdictHandler`, les obligations de l'appelant, et pourquoi le
  compteur compte les verdicts robot plutôt que les étapes de friction.
- [Signaux du navigateur](doc/fr/browser-signals.md) — les détecteurs du contrôleur Stimulus `verdict` dans le
  navigateur, les signaux qui restent à écrire, ce qui n'est jamais collecté ni envoyé, et les projets libres lus comme
  références de conception (motifs seulement).
- [Points d'extension](doc/fr/extension-points.md) — le port `RobotVerdictCounter`, ses invariants et des exemples
  d'adaptateurs.
- [Tests](doc/fr/testing.md) — exécuter les tests depuis le monodépôt ou avec le bundle installé seul, et tester votre
  compteur et vos surfaces.
- [Référence de l'API](doc/fr/reference.md) — chaque type public par couche, avec son rôle et son état.

## Démarrage rapide

```bash
composer require phil/bot-detection-bundle:^0.1@dev   # from a path or VCS repository until published
```

```php
use Phil\BotDetectionBundle\Application\VerdictHandler;
use Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;

$handler = new VerdictHandler(FrictionPolicy::robotOrUnknown(), $signUpRobotVerdictCounter);

$friction = $handler->handle(Verdict::fromSubmitted($request->request->get('verdict')));

if ($friction->adds()) {
    // one more step on this surface: a back-off, a stronger challenge...
}
// then drop the verdict: never log it, never store it
```

## Prérequis

- PHP 8.5 ou plus récent
- `symfony/dependency-injection` et `symfony/config` 8.2

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
