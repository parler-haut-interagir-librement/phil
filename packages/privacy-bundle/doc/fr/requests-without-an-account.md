<!-- phil-dev source-sha256=89120f32f138341b5f2d980a3d901dd05b8fc49519f302d9394f5f59f7b99869 -->
# Demandes sans compte

Une personne qui n'a pas de compte — abonnée à une infolettre, quelqu'un qui a écrit par un formulaire, l'acheteuse d'un
billet — a les mêmes droits qu'un membre : une copie de ce que le site garde à son sujet, et son effacement. Le bundle
la laisse demander par le site, en prouvant par un lien envoyé à son adresse que l'adresse est la sienne.

```yaml
phil_privacy:
    guest_requests:
        enabled: true
        from: 'Privacy <privacy@example.org>'
```

C'est inactif tant que `enabled` ne vaut pas `true`. Une fois activé, rien n'y est facultatif : la construction échoue
sans `symfony/rate-limiter`, sans expéditeur des liens, sans stockage des demandes, sans le verrou ou sans l'horloge,
plutôt que d'ouvrir un formulaire qui envoie du courrier à n'importe qui sans ses limites.

## Les étapes

1. La personne ouvre `phil_privacy_guest_request` (`/privacy-choices/request`), choisit une copie ou un effacement, et
   donne son adresse.
2. Le site répond la même page quelle que soit l'adresse : « si cette adresse peut recevoir du courrier, un lien est en
   route ». Il envoie le lien à l'adresse, normalisée — le domaine en minuscules, la partie avant le @ telle qu'écrite.
3. Le lien ouvre `phil_privacy_guest_confirm` (`/privacy-choices/request/{id}?token=…`), une page qui dit ce qui va se
   passer et demande de confirmer. **Ouvrir le lien ne fait rien** : seul le formulaire de cette page agit, pour qu'un
   analyseur de courrier qui suit les liens ne change rien.
4. Le formulaire télécharge l'archive de ce que le site garde sur l'adresse
   ([export-and-erasure.md](export-and-erasure.md#larchive-dune-réponse-daccès)), ou l'efface de chaque module et le
   dit. Le lien ne fonctionne plus ensuite.

Un effacement qu'un module n'a pas pu terminer garde la demande : la page dit de réessayer dans un moment, avec le même
lien, jusqu'à son expiration ; les effaceurs sont idempotents.

## Les protections

- **Aucune énumération.** Le formulaire répond la même page que l'adresse soit connue ou non. Ce que trouve le lien
  n'est décidé qu'à son ouverture, par la personne qui détient la boîte aux lettres.
- **Une limite par client**, comptée avant toute lecture, adresse invalide comprise : `per_client`, 5 par heure par
  défaut, sur l'adresse IP du client. Au-delà, le formulaire répond `429` avec `Retry-After`.
- **Une limite par adresse** : `per_address`, 3 par jour par défaut. Au-delà, le lien n'est pas envoyé et la page ne le
  dit pas : la limite protège une boîte aux lettres d'être remplie par quelqu'un d'autre.
- **Ni adresse ni IP dans le stockage du limiteur.** Les deux limites ont pour clé un HMAC sous une clé dérivée du
  secret de l'application, dans le réservoir de cache de `guest_requests.cache` (`cache.app`).
- **Un lien fonctionne une fois, peu de temps.** Son secret fait 256 bits aléatoires ; seul son SHA-256 est gardé, et il
  est comparé en temps constant. Il expire après `link_lifetime` (`PT1H`). Une demande expirée est retirée à l'ouverture
  de son lien, et à chaque nouvelle demande.
- **L'adresse est scellée au repos.** La table `phil_privacy_guest_request` la garde chiffrée par libsodium sous une clé
  dérivée du secret de l'application, seulement aussi longtemps que la demande.
- **Une seule exécution à la fois.** Un export ou un effacement s'exécute sous un verrou nommé d'après la demande,
  relue une fois le verrou obtenu.
- **Aucun secret dans un référent ou une file.** Les pages du lien envoient `Referrer-Policy: no-referrer` et ne sont
  jamais gardées par un cache ; l'expéditeur par courriel envoie tout de suite par les transports du service de
  courriel, jamais par une file de messages où le lien serait écrit.
- Les formulaires portent le jeton CSRF sans état du bundle.

## Qui est l'adresse

Les modules exportent et effacent par `DataSubject`. `GuestSubjects` dit quel identifiant est une adresse vérifiée : par
défaut l'adresse elle-même (`EmailGuestSubjects`), pour les modules qui indexent les enregistrements des personnes sans
compte sur leur adresse. Une application dont les modules les indexent autrement nomme son propre service sous
`guest_requests.subjects`.

```php
final readonly class Subscribers implements GuestSubjects
{
    public function subjectOf(string $email): DataSubject
    {
        return DataSubject::fromString('subscriber:'.hash('sha256', $email));
    }
}
```

## Le lien

`MailerGuestLinkSender` rend `@PhilPrivacy/guest/email.txt.twig` — son bloc `subject` est l'objet — et l'envoie depuis
`guest_requests.from`. Une autre façon d'envoyer est un `GuestLinkSender` nommé sous `guest_requests.sender` ; il garde
le secret hors de tout journal et de toute file.

| Clé | Par défaut | Effet |
|---|---|---|
| `enabled` | `false` | Active les demandes. Tant qu'elles sont inactives, leurs pages répondent `404`. |
| `link_lifetime` | `PT1H` | Combien de temps un lien fonctionne. |
| `from` | `null` | L'expéditeur des liens ; exigé par l'expéditeur par courriel. |
| `sender` | `null` | Identifiant de service d'un `GuestLinkSender`. |
| `subjects` | `null` | Identifiant de service d'un `GuestSubjects`. |
| `store` | `null` | Identifiant de service d'un `GuestRequestStore` ; `null`, la table du bundle. |
| `cache` | `cache.app` | Le réservoir de cache où les limites sont comptées. |
| `per_client` | `{ limit: 5, interval: '1 hour' }` | La limite par client. |
| `per_address` | `{ limit: 3, interval: '1 day' }` | La limite par adresse. |

Les pages et le courriel sont dans le domaine `phil_privacy`, en anglais et en français ; remplacez les gabarits sous
`templates/bundles/PhilPrivacy/guest/` pour votre balisage.
