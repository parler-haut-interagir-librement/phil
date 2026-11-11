<!-- phil-dev source-sha256=a3a66614bb3958d959dc0552df24f06332f8743537784a15c95713487821e207 -->
# Le verdict

**Le navigateur rapporte une chose, et une seule : robot ou humain.** Aucun identifiant, aucune empreinte, aucun
hachage, aucun détail de détecteur ne quitte le navigateur.

État : `Phil\BotDetectionBundle\Domain\Verdict\Verdict` est implémenté et testé. Le navigateur le calcule avec le
contrôleur Stimulus `verdict` et ses détecteurs ([browser-signals.md](browser-signals.md)).

## `Verdict`

Une énumération adossée à des chaînes :

| Cas | Valeur | Sens |
|---|---|---|
| `Verdict::Human` | `human` | Les détecteurs du navigateur n'ont trouvé aucune automatisation |
| `Verdict::Robot` | `robot` | Les détecteurs du navigateur ont trouvé de l'automatisation |
| `Verdict::Unknown` | `unknown` | Aucun verdict utilisable : le champ était absent, vide, ou contenait autre chose que les deux valeurs transmises |

`Unknown` est le sort d'un navigateur qui bloque les scripts, comme d'un client qui ne les a jamais exécutés, et d'une
valeur trafiquée.

### Lire ce que le client a soumis : `fromSubmitted()`

```php
$verdict = Verdict::fromSubmitted($request->request->get('verdict')); // ?string

Verdict::fromSubmitted('human');        // Human
Verdict::fromSubmitted('robot');        // Robot
Verdict::fromSubmitted(null);           // Unknown — absent
Verdict::fromSubmitted('');             // Unknown
Verdict::fromSubmitted('Robot');        // Unknown — another case
Verdict::fromSubmitted(' human ');      // Unknown — surrounding whitespace
Verdict::fromSubmitted('human:3f2a9c'); // Unknown — a longer payload
Verdict::fromSubmitted('true');         // Unknown
Verdict::fromSubmitted('unknown');      // Unknown — the unknown case is not a wire value
```

**L'analyse est stricte et ne lève jamais d'exception.** Seules les valeurs transmises exactes `human` et `robot` sont
reconnues. Un verdict qui ne s'analyse pas n'est pas une raison de refuser une requête : il n'y a donc aucune exception
à attraper. La rigueur signifie aussi qu'un client ne peut pas glisser plus qu'un verdict derrière un verdict
(`human:<something>`) : la forme que prendrait un identifiant est refusée comme `Unknown`.

Utilisez `fromSubmitted()` plutôt que `Verdict::from()` ou `tryFrom()` : `from('unknown')` accepterait le cas interne
comme si un client l'avait envoyé, et `from()` lève une exception pour tout le reste.

### `isRobot()`

```php
Verdict::Robot->isRobot();   // true
Verdict::Human->isRobot();   // false
Verdict::Unknown->isRobot(); // false
```

## Invariants

- **Un verdict n'est pas un identifiant.** Il ne porte rien qui puisse distinguer quiconque.
- **Un verdict n'est jamais stocké** — ni avec la requête qu'il tranche, ni dans la session, ni à côté d'une empreinte
  d'appareil que la session détient. Il tranche une requête puis est abandonné
  ([verdict-handling.md](verdict-handling.md)).
- **La seule trace est un compte agrégé des verdicts robot** (`RobotVerdictCounter`), qui ne prend aucun argument.
- **Un verdict est falsifiable.** Il est calculé par du code qui s'exécute chez le client : un robot peut envoyer
  `human`, et le navigateur d'une personne peut être signalé à tort. C'est pourquoi un verdict ne fait jamais qu'ajouter
  de la friction, et ne refuse jamais ([friction.md](friction.md)).
- **Un verdict absent n'est pas un verdict robot.** Il vaut `Unknown` ; qu'il ajoute de la friction est un choix par
  surface, et il n'est jamais compté comme un robot.
