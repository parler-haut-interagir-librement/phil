<!-- phil-dev source-sha256=e20e6aa1457549f739be4f8865478f63c48c64fc89ed8f9929297b2571b3040a -->
# phil/guardian-consent

**Tranches d'âge par juridiction, et consentement parental fonctionnalité par fonctionnalité pour les membres sous l'âge du consentement.**

phil/guardian-consent lit l'âge d'un membre à partir de sa date de naissance, selon les règles d'une juridiction :
l'âge à partir duquel une personne consent pour elle-même, l'âge de la majorité, le fuseau horaire dans lequel un
anniversaire commence, le jour d'anniversaire d'une personne née le 29 février. Il ne garde que le jour où la personne
atteint l'âge du consentement, jamais la date de naissance. Les règles du Québec sont celles par défaut : 14 ans pour
le consentement, 18 pour la majorité. L'application configure toute autre juridiction, comme un État membre qui
applique le RGPD.

Sous l'âge du consentement, chaque fonctionnalité que le site déclare — les messages, le profil public, les petites
annonces — est refusée tant que le titulaire de l'autorité parentale ne l'a pas accordée. Une demande part par courriel
avec un lien de confirmation : signé par la clé de l'application, conservé seulement sous forme d'empreinte à clé,
utilisé une fois, et expirant après un délai configurable. Le tuteur accorde certaines fonctionnalités et refuse les
autres, et peut retirer un consentement en tout temps. Une demande restée sans réponse expire, puis est effacée après
un délai de conservation. Chaque décision est gardée comme une preuve datée : qui a décidé, quoi, quand, sur quelle
version du texte, par quelle demande.

Le jour où un membre atteint l'âge du consentement, les consentements du tuteur cessent de s'appliquer : le membre en
est informé et confirme ses propres choix. Les transactions par carte et les pièces d'identité ne servent pas de
preuve : elles recueillent plus que la finalité n'exige.

C'est une bibliothèque PHP simple, construite sur l'horloge et le répartiteur d'événements PSR. Les mineurs, les
demandes et les preuves sont gardés derrière trois ports, avec des dépôts en mémoire ; le courriel, le lien vers un
adulte qui gère un compte, et les demandes d'accès et d'effacement des outils de vie privée sont aussi des ports. Ses
événements ne portent que des identifiants. Ses services ne gardent aucun état entre les appels et sont sûrs dans un
*worker* de longue durée.

## État

| Partie | État |
|---|---|
| Dates du calendrier, années complètes écoulées, 29 février selon la règle de la juridiction | Implémenté et testé |
| Juridictions : âge du consentement, majorité, fuseau horaire ; Québec par défaut | Implémenté et testé |
| Tranches d'âge à partir d'une date de naissance qui n'est pas conservée | Implémenté et testé |
| Demandes à un tuteur, fonctionnalité par fonctionnalité, refusées par défaut | Implémenté et testé |
| Jetons de confirmation : signés, à empreinte, à usage unique, expirants ; rotation des clés | Implémenté, testé et soumis au fuzzing |
| Retrait par un tuteur, ou par un adulte que nomme un lien | Implémenté et testé |
| Preuves datées de chaque décision | Implémenté et testé |
| Le passage de relais à l'âge du consentement, avec un avis et la confirmation du membre | Implémenté et testé |
| Le balayage quotidien : demandes sans réponse effacées, avis de passage de relais envoyés | Implémenté et testé |
| Accès et effacement, lus comme membre et comme tuteur | Implémenté et testé |
| Dépôts en mémoire | Implémenté et testé |
| Dépôts en base de données, l'envoi de courriels, les pages et les formulaires | Hors de la bibliothèque : l'application, ou un bundle qui s'appuie sur elle, les fournit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les services à construire. |
| [Concepts](doc/fr/concepts.md) | Mineurs, fonctionnalités, demandes, jetons, preuves, statuts, passage de relais et balayage. |
| [Juridictions](doc/fr/jurisdictions.md) | Les règles d'une juridiction, celle par défaut, et comment en configurer d'autres. |
| [Sécurité et vie privée](doc/fr/security.md) | Ce qui est conservé, les jetons, l'accès et l'effacement, les bornes de chaque entrée. |
| [Points d'extension](doc/fr/extension-points.md) | Les dépôts, le notificateur, le lien de tuteur, les événements, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, et comment tester le code qui utilise les consentements. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/guardian-consent
```

```php
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\Feature;
use Phil\GuardianConsent\Guardian;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\NoticeVersion;

$member = new MemberId($user->id);

$consent->register($member, CalendarDate::fromString('2013-05-02'));  // the date of birth is read, not kept

$consent->allows($member, new Feature('messages'));                    // false: refused until a guardian grants it

$consent->request(
    $member,
    new Guardian(new GuardianId($parentAccountId), 'parent@example.org'),
    [new Feature('messages'), new Feature('public-profile')],
    new NoticeVersion('2026-11'),
);                                                                      // the notifier sends the link

// On the page the link opens, then on its form:
$request = $consent->open($token);
$consent->decide($token, [new Feature('messages')]);                    // messages granted, public profile refused
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
