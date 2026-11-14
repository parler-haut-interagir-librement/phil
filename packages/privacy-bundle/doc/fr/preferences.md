<!-- phil-dev source-sha256=9087ddd441727a27f72abc3832d7742fedf700349ef6cad4db683d255bb1f67e -->
# Export et effacement depuis les préférences

Un membre exerce ses droits depuis la page `phil_privacy_preferences` : télécharger tout ce que le site détient à son
sujet, en une archive ZIP avec un `README.txt` lisible et un fichier JSON par module
([export-and-erasure.md](export-and-erasure.md#larchive-dune-réponse-daccès)), et supprimer son compte. **Les deux sont
actifs par défaut**, sur chaque site. Quand l'application l'active, la même page demande au membre si les courriels qui
lui sont envoyés peuvent être suivis ([email-tracking.md](email-tracking.md)).

Le bundle monte lui-même ses pages, devant les routes de l'application, sous `pages.prefix` (`/privacy-choices`) :

| Route | Chemin | Méthodes |
|---|---|---|
| `phil_privacy_preferences` | `/` | `GET` |
| `phil_privacy_export` | `/export` | `POST` |
| `phil_privacy_email_tracking` | `/email-tracking` | `POST` ; un `404` tant que `email_tracking.enabled` n'est pas actif |
| `phil_privacy_erasure` | `/erasure` | `GET`, `POST` |
| `phil_privacy_erasure_cancel` | `/erasure/cancel` | `POST` |
| `phil_privacy_consent` | `/consent` | `GET` |
| `phil_privacy_consent_decide` | `/consent` | `POST` |
| `phil_privacy_cookies` | `/cookies` | `GET` |
| `phil_privacy_guest_request` | `/request` | `GET`, `POST` |
| `phil_privacy_guest_confirm` | `/request/{id}` | `GET`, `POST` |

Pour les monter ailleurs, mettez `pages.auto_mount: false` et importez `@PhilPrivacyBundle/config/routes.php`. Aucune
adresse ne porte quoi que ce soit sur une personne : le lien d'une demande faite sans compte porte un identifiant
aléatoire et un secret à usage unique.

Les préférences doivent être derrière un pare-feu, pour que le bundle sache qui demande. Le consentement, la politique
des témoins et les demandes sans compte ([requests-without-an-account.md](requests-without-an-account.md)) sont ouverts
à tous.

## Le membre

`CurrentDataSubject` dit qui est le membre courant. Par défaut, c'est l'utilisateur connecté, dont la classe implémente
`DataSubjectUser` :

```php
final class User implements UserInterface, DataSubjectUser
{
    public function dataSubjectIdentifier(): string
    {
        return $this->id->toRfc4122();
    }
}
```

Une classe d'utilisateur qui ne l'implémente pas est refusée plutôt que lue par `getUserIdentifier()`, qui est souvent
une adresse courriel. Une autre source est un service nommé dans `pages.current_subject`.

## La confirmation

La confirmation d'une suppression utilise le mode sudo de Symfony : `IS_AUTHENTICATED_RECENTLY`, sur la page et sur le
formulaire. Une session qui n'a pas fait ses preuves récemment est envoyée au `re_authentication_entry_point` du
pare-feu, et revient à la page. Sans point d'entrée, la demande est refusée par un `403` : configurez-en un.

```yaml
security:
    firewalls:
        main:
            re_authentication_entry_point: form_login
```

Les formulaires sont protégés par le jeton CSRF sans état `phil_privacy`. Un seul export à la fois par membre.

## Désactiver un droit

> [!CAUTION]
> **Laissez les deux droits actifs.** En désactiver un retire un droit légal à tous les membres du site d'un coup : ils
> ne peuvent plus obtenir leurs données, ou plus supprimer leur compte, sans vous écrire, et vous devez alors répondre à
> chaque demande à la main dans le délai légal.

**C'est un geste destructeur**, et le bundle le traite comme un hébergeur de code traite la suppression d'un dépôt : il
n'y a pas de booléen. La configuration contient la phrase entière, et toute autre valeur arrête la construction.

```yaml
phil_privacy:
    rights:
        export:
            disable: 'I understand that members of this site will no longer be able to export their personal data from their preferences'
        erasure:
            disable: 'I understand that members of this site will no longer be able to erase their account and personal data from their preferences'
```

Un droit désactivé lève un avertissement qui encadre la sortie d'erreur à chaque construction du conteneur, et une ligne
rouge `DANGER` avant chaque commande de la console. Ses routes répondent `404` et sa section quitte la page. Les
commandes `phil:privacy:export` et `phil:privacy:erase` restent, pour la personne qui répond aux demandes à la main.
