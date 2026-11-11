<!-- phil-dev source-sha256=eb7d1de13d9bb9cdaa30cde3f63d1ec4ced8dc6fe06e64ca905d04bb125ae9b6 -->
# Traiter un verdict, et le compteur de verdicts robot

État : `VerdictHandler` est implémenté et testé ; `RobotVerdictCounter` est un port implémenté par l'application
([extension-points.md](extension-points.md)).

## `VerdictHandler`

`Phil\BotDetectionBundle\Application\VerdictHandler` tranche une requête d'après son verdict :

```php
final readonly class VerdictHandler
{
    public function __construct(FrictionPolicy $policy, RobotVerdictCounter $counter);

    public function handle(Verdict $verdict): Friction;
}
```

`handle()` :

1. Si le verdict est `Robot`, incrémente le compteur une fois.
2. Retourne `$policy->frictionFor($verdict)`.

```php
$friction = $this->signUpVerdicts->handle(Verdict::fromSubmitted($request->request->get('verdict')));

if ($friction->adds()) {
    $this->signUpLimiter->addBackoffStep($throttleKey); // your meaning of one step on this surface
}
// The verdict goes no further: not logged, not in the session, not stored.
```

**Les obligations de l'appelant :** appliquer la friction à la requête, puis abandonner le verdict, sans l'écrire dans
le journal des requêtes, dans la session ni dans rien qui soit indexé sur une personne.

| Verdict | Politique | Compteur | Retour |
|---|---|---|---|
| `Robot` | n'importe laquelle | +1 | `Step` |
| `Human` | n'importe laquelle | inchangé | `None` |
| `Unknown` | `robotOnly()` | inchangé | `None` |
| `Unknown` | `robotOrUnknown()` | **inchangé** | `Step` |

## `RobotVerdictCounter`

`Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter` est le compte agrégé des verdicts robot, et **la seule
trace que laisse un verdict**.

```php
interface RobotVerdictCounter
{
    public function increment(): void;
}
```

### Pourquoi `increment()` ne prend aucun argument

Aucun compte, aucune session, aucune adresse IP, aucun agent utilisateur, aucun identifiant de commande ou de requête ne
peut atteindre le compteur, parce que le port n'a nulle part où les mettre. Un compteur qui doit être séparé l'est sans
personne dedans :

- **par surface** : l'application branche un compteur par surface (inscription, paiement, un site mesuré) ;
- **par fenêtre de temps** : l'adaptateur répartit les incréments dans des fenêtres.

### Pourquoi il compte les verdicts robot et non les étapes de friction

Un verdict absent ou mal formé n'est pas compté, **même quand `FrictionPolicy::robotOrUnknown()` lui ajoute une étape**.
Un détecteur qui se tait (un déploiement brisé, un script bloqué) se voit alors comme un compte qui chute, au lieu
d'être masqué par des verdicts qui ne sont jamais arrivés. Surveillez le compte par rapport à son taux habituel, et
déclenchez une alerte à la fois sur une poussée et sur une fenêtre sans aucun verdict robot pour une surface qui en a
d'ordinaire.

Une application qui veut aussi le nombre d'étapes ajoutées les compte dans le limiteur qui les absorbe.
