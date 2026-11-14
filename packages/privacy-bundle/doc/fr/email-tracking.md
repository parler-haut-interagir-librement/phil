<!-- phil-dev source-sha256=aedd615541be69625f74437302d7c9e5bf3b589165cdd792151041a03e7a2a64 -->
# Courriels suivis

Un courriel peut apprendre à son expéditeur qu'il a été ouvert — une image invisible chargée depuis le serveur de
l'expéditeur — et lesquels de ses liens ont été cliqués — chaque lien passant d'abord par l'expéditeur. L'un et l'autre
lisent quelque chose sur l'appareil du destinataire et le rattachent à une personne : ils attendent donc le
consentement de cette personne. Le bundle le demande à chaque membre, sur sa page de préférences, et répond au code qui
envoie les courriels.

**Inactif par défaut.** Un site qui ne suit pas ses courriels ne demande rien ; un site qui le fait active le
consentement :

```yaml
phil_privacy:
    email_tracking:
        enabled: true
        version: '1'          # the version of the text the member reads
```

## Ce que voit le membre

La page de préférences ([preferences.md](preferences.md)) gagne une section de deux cases, une par fin, toutes deux
décochées tant que le membre ne les coche pas :

- compter quand j'ouvre un courriel ;
- suivre les liens sur lesquels je clique dans un courriel.

Le formulaire est envoyé à `phil_privacy_email_tracking` (`/email-tracking`), avec le jeton CSRF sans état du bundle, et
revient à la page avec un `303`. Une case laissée décochée refuse : retirer son consentement se fait par le même
formulaire, aussi simplement que l'accorder. La page affiche la version du texte et la date du dernier choix.

## Ce que demande l'expéditeur

`EmailTrackingConsents` est un service public. Le code qui envoie un courriel le consulte, avec l'identifiant de la
personne concernée du membre, avant d'activer un pixel ou des liens redirigés :

```php
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;

$opens = $this->consents->opens($memberId);
$clicks = $this->consents->clicks($memberId);
```

Chaque réponse est non, sauf si le membre a répondu oui sous la version courante du texte : un membre qui n'a jamais
choisi, un identifiant qu'aucun membre ne peut avoir (vide, trop long, avec des espaces) ou un choix fait sous une
version antérieure répondent tous non. Chaque question lit une ligne par sa clé primaire ; rien n'est conservé d'une
requête à l'autre.

Avec `phil/notifications-bundle`, dont les résumés interrogent le port `EmailTrackingConsent`, l'application lui
délègue :

```php
use Phil\NotificationsBundle\Application\Contract\EmailTrackingConsent;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;

final readonly class MemberEmailTracking implements EmailTrackingConsent
{
    public function __construct(private EmailTrackingConsents $consents) {}

    public function opens(string $recipient): bool
    {
        return $this->consents->opens($recipient);
    }

    public function clicks(string $recipient): bool
    {
        return $this->consents->clicks($recipient);
    }
}
```

```yaml
phil_notifications:
    digest:
        tracking_consent: App\Privacy\MemberEmailTracking
```

Le destinataire d'une notification doit alors être l'identifiant de la personne concernée du membre, celui que donne
`DataSubjectUser`.

## La version du texte

Un membre a accepté un texte. Quand le texte change — une autre fin, un autre sous-traitant —, changez `version` :
chaque choix antérieur cesse de compter, rien n'est suivi, et la page redemande avec les deux cases décochées. Une
version est faite de lettres, de chiffres, de `.`, de `_` et de `-`, 32 au plus, et commence par une lettre ou un
chiffre ; toute autre valeur fait échouer la construction.

## L'enregistrement

La table `phil_privacy_email_tracking`, ajoutée au schéma que génère l'ORM, garde une ligne par membre : la personne
concernée, la version, `opens`, `clicks` et la date du choix dans une colonne `datetime_utc_immutable`. Le dernier choix
remplace le précédent ; un choix qui refuse les deux reste, comme preuve datée du retrait. Un autre stockage est un
`EmailTrackingStore` nommé sous `email_tracking.store`
([extension-points.md](extension-points.md#emailtrackingstore)).

Une fois activé, le consentement a besoin d'un stockage et de l'horloge ; une partie manquante fait échouer la
construction avec une `LogicException` qui la nomme.

## Export et effacement

`EmailTrackingConsents` est aussi le module `email_tracking` des droits du membre : son export contient
`email_tracking.json` avec la version, `opens`, `clicks` et la date en UTC, et son effacement supprime la ligne.
L'effacement est idempotent, comme celui de chaque module doit l'être ([export-and-erasure.md](export-and-erasure.md)).
