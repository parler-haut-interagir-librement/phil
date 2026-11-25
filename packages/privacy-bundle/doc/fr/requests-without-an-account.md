<!-- phil-dev source-sha256=5eb701658c105d54242263cb3d484153fa8dd360fc8ccc1797a2df53bf66eced -->
# Demandes sans compte

Une personne qui n'a pas de compte — abonnée à une infolettre, quelqu'un qui a écrit par un formulaire, l'acheteuse d'un
billet — a les mêmes droits qu'un membre : une copie de ce que le site garde à son sujet, et son effacement. Le bundle
la laisse demander par le site, en prouvant par un lien envoyé à son adresse que l'adresse est la sienne.

```yaml
phil_privacy:
    public_origin: '%env(APP_PUBLIC_ORIGIN)%'   # https://example.org
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
- **Ni adresse ni IP dans le stockage du limiteur.** Chaque limite a pour clé un HMAC-SHA-256 sous une clé à elle,
  dérivée du secret de l'application par HKDF (voir [Modèle de menaces](threat-model.md#les-clés)), dans le réservoir de cache de `guest_requests.cache` (`cache.app`).
- **Un lien fonctionne une fois, peu de temps.** Son secret fait 256 bits aléatoires ; seul son SHA-256 est gardé, et il
  est comparé en temps constant. Il expire après `link_lifetime` (`PT1H`). Une demande expirée est retirée à l'ouverture
  de son lien, et à chaque nouvelle demande.
- **L'adresse est scellée au repos.** La table `phil_privacy_guest_request` la garde chiffrée par libsodium sous une clé
  dérivée du secret de l'application par HKDF, seulement aussi longtemps que la demande.
- **Une seule exécution à la fois.** Un export ou un effacement s'exécute sous un verrou nommé d'après la demande,
  relue une fois le verrou obtenu. Le verrou dure 300 secondes et est renouvelé avant chaque module et avant le retrait de
  la demande : une exécution dont le verrou a été pris par une autre s'arrête, garde la demande, et répond comme à un lien
  qui ne l'ouvre plus ([Verrous et longs travaux](export-and-erasure.md#verrous-et-longs-travaux)).
- **Aucun secret dans un référent ou une file.** Les pages du lien envoient `Referrer-Policy: no-referrer` et ne sont
  jamais gardées par un cache ; l'expéditeur par courriel envoie tout de suite par les transports du service de
  courriel, jamais par une file de messages où le lien serait écrit.
- **Le lien mène au site, quoi que la requête ait nommé.** Voir [Où mène le lien](#où-mène-le-lien).
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

## Où mène le lien

Le lien est une adresse absolue, et une requête nomme son propre hôte dans son en-tête `Host`. Sans précaution, quelqu'un
demande un lien pour l'adresse d'une autre personne en nommant son propre hôte : la personne reçoit un vrai lien qui
mène à cet hôte, et un clic en livre le secret. `MailerGuestLinkSender` construit donc le lien sur `public_origin`,
l'adresse canonique du site, par `GuestLinkOrigin` : le contexte du routeur nomme cette origine — schéma, hôte et
port — pendant la génération du lien, et le contexte de la requête revient aussitôt ; le chemin de base de l'application
est conservé. `phil/url` lit l'origine au démarrage du noyau : une adresse `http` ou `https` avec un nom d'hôte, sans
adresse IP, sans chemin, sans requête ni fragment ; `localhost` seulement en mode débogage.

Sans `public_origin`, le lien prend l'hôte de la requête, que `framework.trusted_hosts` doit borner :

```yaml
framework:
    trusted_hosts: ['^example\.org$']
```

Une requête vers un autre hôte est alors refusée avec `400`. Dans l'environnement `prod`, tant que les demandes sont
activées avec l'expéditeur par courriel, le bundle refuse de démarrer quand ni `public_origin` ni
`framework.trusted_hosts` n'est réglé. Un `GuestLinkSender` propre à l'application construit lui-même son lien, et doit
la même précaution.

Les pages et le courriel sont dans le domaine `phil_privacy`, en anglais et en français ; remplacez les gabarits sous
`templates/bundles/PhilPrivacy/guest/` pour votre balisage.

## Comme un workflow

Avec [`erasure_workflow`](erasure-workflow.md#demandes-faites-sans-compte) activé, le formulaire d'un lien d'effacement
ouvert ouvre une demande d'effacement au lieu d'exécuter chaque module d'un coup, et la page qui suit donne un lien de
progression avec un secret propre. Les messages ne portent que l'identifiant de la demande d'effacement, jamais
l'adresse ; chaque *worker* lit l'adresse dans la demande faite sans compte, qui reste, son lien ne l'ouvrant plus,
jusqu'à ce que l'effacement soit fermé, ou 90 jours au plus.
