<!-- phil-dev source-sha256=fc604385720d02723e333bba970d68ee8ae8461290221746629095f926762d37 -->
# Référence de l'API

Chaque type public du bundle, par couche, avec son rôle et son état. Les espaces de noms sont relatifs à
`Phil\BotDetectionBundle`.

## Bundle

| Type | Rôle | État |
|---|---|---|
| `PhilBotDetectionBundle` | La classe du bundle ; alias d'extension `phil_bot_detection` | Aucun service, aucune configuration ([configuration.md](configuration.md)) |

## Domaine

| Type | Rôle | État |
|---|---|---|
| `Domain\Verdict\Verdict` | Énumération adossée à des chaînes `Human` (`human`), `Robot` (`robot`), `Unknown` (`unknown`) ; `fromSubmitted(?string): self`, stricte et sans exception ; `isRobot()` | Implémenté et testé ([verdict.md](verdict.md)) |
| `Domain\Friction\Friction` | Énumération pure `None`, `Step`, sans cas de refus ; `adds()` | Implémenté et testé ([friction.md](friction.md)) |
| `Domain\Friction\FrictionPolicy` | `robotOnly()`, `robotOrUnknown()`, `frictionFor(Verdict): Friction` ; `unknownAddsFriction` public | Implémenté et testé ([friction.md](friction.md)) |
| `Domain\Counter\RobotVerdictCounter` | Interface : `increment(): void`, sans argument | Port seulement ([extension-points.md](extension-points.md)) |

## Application

| Type | Rôle | État |
|---|---|---|
| `Application\VerdictHandler` | `__construct(FrictionPolicy, RobotVerdictCounter)`, `handle(Verdict): Friction` : compte un verdict robot et retourne la friction | Implémenté et testé ([verdict-handling.md](verdict-handling.md)) |

## Navigateur

| Fichier | Rôle | État |
|---|---|---|
| `assets/src/verdict_controller.js` | Le contrôleur Stimulus `verdict` : exécute les détecteurs et laisse `human` ou `robot` sur l'élément racine et dans son champ | Implémenté, testé avec Node |
| `assets/src/detectors.js` | Les détecteurs et leur combinaison en un verdict | Implémenté, testé avec Node ; aucun code tiers inclus |

## Pas encore dans le squelette

| Pièce | État |
|---|---|
| Les signaux de la conception qui ne sont pas encore écrits (fonctions natives réécrites, fenêtre comparée à un *worker*, permissions, voix de synthèse, WebRTC, cohérence du matériel et de l'écran) | Non écrits ([browser-signals.md](browser-signals.md)) |
| Services, configuration, fonction Twig pour le contrôleur | Non écrits |
