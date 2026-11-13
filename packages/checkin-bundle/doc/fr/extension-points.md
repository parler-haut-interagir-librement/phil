<!-- phil-dev source-sha256=700007d7894ec3cda02c7cf1beebadf4684eb820414ffeb36f44e412dadd3e86 -->
# Points d'extension

## Résolveurs

Un service qui implémente `Phil\Checkin\Contract\CredentialResolver` reçoit l'étiquette
`phil_checkin.credential_resolver` par autoconfiguration ; le poste interroge les résolveurs étiquetés dans l'ordre de
leur priorité. Un résolveur répond null pour un code qu'il ne connaît pas. Voyez le `doc/extension-points.md` de
`phil/checkin` pour son contrat.

```yaml
services:
    App\Checkin\TicketResolver:
        tags: [{ name: phil_checkin.credential_resolver, priority: 10 }]
```

## Règles d'admission

La règle d'un point de contrôle est une expression de l'ExpressionLanguage de Symfony que doit satisfaire un titre
d'accès pour y entrer, en plus de la politique. La configuration les donne par point de contrôle (voyez
[Configuration](configuration.md#règles)) ; une application qui les garde ailleurs — dans sa base de données, modifiées
par son personnel — implémente `Phil\CheckinBundle\Application\Contract\AdmissionRules` et nomme son service dans
`rules.provider` :

```php
final readonly class DatabaseRules implements AdmissionRules
{
    public function ruleFor(CheckpointId $checkpoint): ?string
    {
        return $this->checkpoints->rule($checkpoint->value); // null: the policy alone
    }
}
```

Le bundle vérifie les règles de sa configuration à la compilation du conteneur ; il ne peut pas vérifier celles d'un
fournisseur, qui vérifie donc chacune avec `CheckpointRules::lint()` quand le personnel l'enregistre. À la porte, une
règle qui ne s'analyse pas ou qui échoue refuse le titre d'accès.

## Attributs des titres d'accès

`Phil\CheckinBundle\Application\Contract\CredentialAttributes` répond à ce que les règles lisent comme `attributes` :
le produit d'un billet, une tranche d'âge, une décharge signée, par l'identifiant du titre d'accès. Nommez le service
dans `rules.attributes`. Il est interrogé une fois par entrée lue à un point de contrôle qui a une règle, et une fois
par titre d'accès du manifeste d'un tel point de contrôle : répondez depuis un cache ou une seule requête quand le
manifeste est gros.

## Catalogue

Un catalogue implémente `Phil\Checkin\Contract\CredentialCatalog` : les titres d'accès d'un point de contrôle avec
leurs codes, pour les manifestes, et un titre par identifiant, pour confirmer une lecture faite hors ligne. Le service
nommé par `catalog` est le seul interrogé. Sans lui, les services qui implémentent l'interface reçoivent l'étiquette
`phil_checkin.credential_catalog` par autoconfiguration et sont interrogés ensemble dans l'ordre de leur priorité : le
manifeste d'un point de contrôle porte les titres d'accès de tous, et une lecture faite hors ligne est confirmée par le
premier qui en connaît l'identifiant. Sans catalogue nommé ni étiqueté, les postes n'enregistrent qu'en ligne.

## Brancher l'admission d'un module

Un module qui admet ses propres titres d'accès — billets, inscriptions, adhésions, insignes — les branche aux postes par
un seul service, à la fois `CredentialResolver` et `CredentialCatalog`. L'autoconfiguration l'étiquette deux fois ;
l'application ne configure rien, et plusieurs modules se branchent côte à côte :

```php
final readonly class TicketAdmission implements CredentialResolver, CredentialCatalog
{
    public function resolve(CredentialCode $code): ?Credential
    {
        $ticket = $this->tickets->bySecret($code->value);

        return null === $ticket ? null : $this->credential($ticket);
    }

    public function forCheckpoint(CheckpointId $checkpoint): iterable
    {
        foreach ($this->tickets->admittedAt($checkpoint->value) as $ticket) {
            yield new CatalogEntry(CredentialCode::of($ticket->secret), $this->credential($ticket));
        }
    }

    public function find(string $credentialId): ?Credential
    {
        $ticket = str_starts_with($credentialId, 'ticket:') ? $this->tickets->get(substr($credentialId, 7)) : null;

        return null === $ticket ? null : $this->credential($ticket);
    }

    private function credential(Ticket $ticket): Credential
    {
        return Credential::of('ticket:'.$ticket->id, $ticket->holderName, validFrom: $ticket->opensAt, validUntil: $ticket->closesAt);
    }
}
```

| Ce que le module répond | Par |
|---|---|
| Quel code est quel titre d'accès | `resolve()` : null pour un code qu'il ne connaît pas, afin que le résolveur suivant soit interrogé. |
| Quels titres d'accès un point de contrôle admet, pour les appareils hors ligne | `forCheckpoint()` : une entrée par code ; le manifeste garde ceux qu'admettent la politique et la règle du point de contrôle. |
| Le titre d'accès d'une lecture faite hors ligne | `find()` : par l'identifiant que le module lui a donné. |
| Quand et combien de fois un titre d'accès ouvre un point de contrôle | Le `Credential` lui-même : sa fenêtre, ses entrées, s'il est révoqué. |
| Des conditions propres — un produit, un âge | `CredentialAttributes` et la règle du point de contrôle. |
| Ce qui suit une admission — un insigne, une récompense | Un écouteur de `CheckInRecorded`. |

Le module préfixe les identifiants qu'il donne, comme `ticket:` ci-dessus, pour que deux modules ne répondent jamais du
même titre d'accès. Le poste, le registre, la politique, les règles, les manifestes, le journal et la synchronisation
sont ceux du bundle : le module apporte ce qu'est un titre d'accès, rien d'autre.

## Registre

`store` nomme un service qui implémente `Phil\Checkin\Contract\CheckInLedger` pour garder les enregistrements ailleurs
que dans la table du bundle. `DoctrineCheckInLedger` sert de référence : une clé primaire sur l'identifiant de la
lecture et une clé unique sur le titre d'accès et la séquence.

## Voter

Un *voter* de l'application accorde `PhilCheckinBundle::SCAN` (`PHIL_CHECKIN_SCAN`) sur un `CheckpointId` : qui garde
quelle porte. Voyez [Installation](installation.md#la-sécurité).

## Lecteurs

Un paquet qui lit les codes autrement — une caméra, le NFC — déclenche `phil-checkin:code` avec `{code}` sur
l'élément du contrôleur du poste ; le contrôleur l'envoie, ou le décide hors ligne, comme un code tapé. Voyez
[JavaScript](javascript.md).

## Codes que le manifeste ne peut pas contenir

Hors ligne, un code que le manifeste ne contient pas est proposé à la page par `phil-checkin:lookup` ; un paquet qui le
reconnaît — un code qui porte son titre d'accès, signé — répond avec l'entrée qu'il représente. Voyez
[JavaScript](javascript.md#répondre-à-phil-checkinlookup).

## Réactions

Le contrôleur déclenche `phil--checkin-bundle--checkin-desk:checked` après chaque lecture, pour que la page ou un
paquet y réagisse. Côté serveur, chaque lecture que les points de terminaison enregistrent — en ligne, ou issue d'un
journal synchronisé — est transmise sous forme de `Phil\CheckinBundle\Application\Event\CheckInRecorded` au
répartiteur d'événements de l'application, quand elle en a un :

```php
#[AsEventListener]
final readonly class HandOutBadge
{
    public function __invoke(CheckInRecorded $scan): void
    {
        if ($scan->changesAttendance() && Direction::In === $scan->direction) {
            $this->badges->queue($scan->credentialId, $scan->checkpoint->value);
        }
    }
}
```

L'événement ne porte que des identifiants — lecture, titre d'accès, point de contrôle, appareil —, le sens, le résultat,
si la décision a été prise hors ligne et si le serveur a décidé autrement. Les rejeux et les doublons ne sont pas
transmis de nouveau. Une réaction lente passe par Messenger, avec les identifiants pour message.

## Paquets construits sur le bundle

Le bundle est le noyau que d'autres paquets prolongent sans le modifier. Deux sont prévus :

| Paquet | Ce qu'il apporte | Par |
|---|---|---|
| `phil/qr-attendance` | Des codes de billet signés avec Ed25519, valides hors ligne sans entrée de manifeste ; un code tournant de courte durée affiché dans une salle, que les participants lisent | Un `CredentialResolver` qui vérifie un code signé ; un écouteur de `phil-checkin:lookup` qui le vérifie dans le navigateur ; son catalogue qui trouve les titres d'accès signés par identifiant ; pour le code de la salle, son propre point de terminaison sur le `CheckInLedger` et `CheckpointDesks` |
| Un enregistrement pour les jeux de rôle grandeur nature | Des personnages, des décharges et des trousses remises à la porte | `CredentialAttributes` qui répond pour la décharge et le personnage ; une règle comme `attributes['waiver'] == true` ; un écouteur de `CheckInRecorded` qui prépare la trousse ; un lecteur qui déclenche `phil-checkin:code` |

Aucun des deux n'est dans le bundle : le bundle ne nomme ni l'un ni l'autre, et ce dont ils ont besoin est listé
ci-dessus.
