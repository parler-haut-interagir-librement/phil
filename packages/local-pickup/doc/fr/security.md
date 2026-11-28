<!-- phil-dev source-sha256=0a7edaa27f073a449b08bc84507f0b3b225ca1f9c800429292f7c37c4c323bc8 -->
# Sécurité et vie privée

## Le secret

`PickupCodes` reçoit le secret qui sert de clé aux empreintes, d'au moins 32 octets ; gardez-le avec les autres secrets
de l'application, jamais dans la base de données. Le paramètre est marqué `#[\SensitiveParameter]`, de sorte qu'il reste
hors des traces d'appels. Changer le secret rend illisibles les codes déjà envoyés : envoyez-les de nouveau avec
`Pickups::resend()`.

## Ce qui est stocké

| Donnée | Stockée | Où |
|---|---|---|
| Le code | Jamais | Seulement dans le `ReadyNotice` envoyé au client |
| L'empreinte du code | Tant que le ramassage est prêt | La ligne du ramassage ; effacée quand il est ramassé ou expire |
| Les mauvaises tentatives | Oui | La ligne du ramassage |
| Le nom de la personne désignée pour ramasser | Jusqu'à l'effacement | La ligne du ramassage |
| Qui a ramassé, et le nom désigné | Jusqu'à l'effacement | Le journal des remises |

Le message `Message\NotifyPickupReady` porte le numéro de commande et rien d'autre : aucun code, aucun nom, aucune adresse
n'attend dans une file. `IssuedCode`, `ScannedCode` et `HandOverRequest` cachent le code à `var_dump()` et à
`print_r()`.

## Les limites

| Limite | Valeur |
|---|---|
| Code | 8 caractères, 40 bits aléatoires |
| Mauvaises tentatives avant le verrouillage du code | De 1 à 20, 5 par défaut |
| Code saisi lu | 32 octets au plus |
| Charge utile numérisée lue | 97 octets au plus |
| Lot d'expiration | De 1 à 1 000 ramassages |
| Plages lues | Les bornes de phil/reservation : 10 000 candidates et 1 000 plages par appel |
| Textes | 500 octets ; identifiants de 64 ou 255 octets |

Avec cinq tentatives, deviner un code réussit environ une fois sur 220 milliards d'essais. Les lignes stockées et les
charges utiles numérisées sont lues strictement — tout champ manquant, de mauvais type, hors bornes ou incohérent avec
l'état lève `InvalidPickup` — et les deux lecteurs sont soumis au fuzzing.

## Renseignements personnels

`PickupPersonalData` sert le module de vie privée d'une application : son `export()` donne les ramassages et les remises
d'un client avec les noms des personnes qui ramassent, et son `erase()` efface les noms des personnes désignées pour
ramasser et de celles qui ont ramassé, en gardant ce qui a été remis, où et quand, pour la comptabilité. Il déclare
chaque stockage comme `redacted` et peut être relancé sans dommage. Une application qui utilise phil/privacy-bundle
l'enveloppe dans son propre `PersonalDataExporter` et son propre `PersonalDataEraser`, sous le nom de module qu'elle
choisit :

```php
use Phil\LocalPickup\PickupPersonalData;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

final readonly class PickupEraser implements PersonalDataEraser
{
    public function __construct(private PickupPersonalData $data)
    {
    }

    public function module(): ModuleName
    {
        return ModuleName::fromString('local-pickup');
    }

    public function erase(DataSubject $subject): ErasureReport
    {
        return ErasureReport::of('local-pickup', array_map(
            static fn (string $effect): ErasureEffect => ErasureEffect::from($effect),
            $this->data->erase($subject->value),
        ));
    }
}
```
