<!-- phil-dev source-sha256=2c6f484866697732a4b5a63826ebc58d390d350fb299567de266bc57a8438dd1 -->
# phil/newsletter-bundle

**L'infolettre d'un site dans Symfony : on s'abonne, on confirme, on choisit ses listes, on se met en pause ou on s'en va ; les éditeurs écrivent en Markdown ; les messages partent par Symfony Mailer à un rythme que les fournisseurs de courriel acceptent.**

phil/newsletter-bundle garde des listes et leurs abonnés, avec une double confirmation quand une liste l'exige, et
envoie des campagnes écrites en Markdown, mises en page par la mise en page du bundle ou par un gabarit qu'un
administrateur ou un membre a écrit, produit dans le bac à sable de Twig. Chaque message porte la désinscription en un
clic que les clients de courriel montrent comme un bouton, et renvoie vers un centre de préférences où un abonné choisit
ses listes, fait une pause de quelques semaines ou s'en va, en disant pourquoi s'il le souhaite — une raison comptée
sans rien qui désigne la personne. Les messages partent de profils d'expéditeur : une adresse, un transport de Mailer,
les heures d'envoi, un plafond quotidien et un rythme par domaine de destinataire. Les rebonds reviennent dans une boîte
IMAP lue par `phil/bounce-parser`, ou par le webhook d'un fournisseur au moyen de RemoteEvent de Symfony, et une adresse
qui a rebondi ou s'est plainte n'est plus jamais écrite. Les clics sont comptés dans un total par lien par défaut ;
les compter par destinataire est un choix explicite d'une campagne, et les machines qui ouvrent les liens avant les
personnes sont écartées sans lire d'adresse IP ni d'agent utilisateur. Rien ne suit l'ouverture d'un message.

Il convient à tout site Symfony qui écrit à ses membres ou à ses lecteurs : les nouvelles d'un club, les annonces d'une
boutique, les mises à jour d'un jeu. Une application peut servir plusieurs sites, chacun avec son propre public, en
partageant des gabarits. Avec `phil/automations`, le bundle offre ses déclencheurs et ses actions d'envoi et de liste,
pour les séquences de bienvenue et les suivis ; avec `phil/privacy-bundle`, sa part des demandes d'accès et d'effacement ;
avec `phil/standard-webhooks-bundle`, des webhooks signés de ses événements d'audience vers d'autres services.

Le bundle conserve ses données dans des tables d'une connexion Doctrine DBAL, sur PostgreSQL, MySQL, MariaDB ou SQLite.
Les adresses sont validées par `phil/email-address`, les adresses Web lues par `phil/url`, le Markdown compilé et
assaini par `phil/markup`. Les messages, les tâches de Messenger et les webhooks ne portent que des identifiants ; les
liens signés des messages sont vérifiés en temps constant. Les services ne gardent rien entre les requêtes, de sorte que
le bundle fonctionne sur des *workers* de longue durée.

## État

| Partie | État |
|---|---|
| Listes, abonnés, appartenances, double confirmation, multisite, identifiants externes | Implémenté et testé |
| Le formulaire d'abonnement, la confirmation, la désinscription en un clic (RFC 8058), le centre de préférences avec pause et raison anonyme | Implémenté et testé |
| Campagnes en Markdown, la mise en page de la maison, gabarits produits dans `Twig\Sandbox\Sandbox`, l'éditeur Markdown et son aperçu | Implémenté et testé |
| L'envoi par Messenger et Symfony Mailer, profils d'expéditeur à un transport chacun, fenêtres d'envoi, plafonds quotidiens, rythme par domaine de destinataire | Implémenté et testé |
| Le suivi des clics dans un total par défaut ou par destinataire, machines écartées | Implémenté et testé |
| Rebonds d'une boîte IMAP et de `phil/bounce-parser`, VERP, webhooks de fournisseurs par RemoteEvent, suppression | Implémenté et testé |
| Les déclencheurs et les actions de `phil/automations`, le module de vie privée de `phil/privacy-bundle`, les webhooks d'audience | Implémenté et testé |
| Le stockage Doctrine DBAL, testé sur MySQL 8.4 et PostgreSQL 17 ; une cible de fuzzing pour les liens signés | Implémenté et testé |
| Des segments choisis par un paquet d'audience | Un port, `SegmentResolver` ; celui du bundle lit des listes entières |
| Une administration des listes, des campagnes et des gabarits | Hors du bundle : l'application la construit sur les services |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la recette, l'enregistrement, les routes, les tables, les *workers*. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Abonnements](doc/fr/subscriptions.md) | Les listes, la double confirmation, les limites du formulaire, les pages, le départ, la pause, les raisons, les sites. |
| [Campagnes](doc/fr/campaigns.md) | L'écriture, l'éditeur Markdown, les mises en page, les gabarits en bac à sable, l'envoi, les profils d'expéditeur. |
| [Suivi](doc/fr/tracking.md) | Les clics en total ou par destinataire, les machines écartées, ce qui n'est jamais suivi. |
| [Rebonds](doc/fr/bounces.md) | La boîte IMAP, VERP, les webhooks de fournisseurs, les rebonds souples et durs, la suppression. |
| [Intégrations](doc/fr/integrations.md) | Les automatisations, les demandes de vie privée, les webhooks d'audience, les segments. |
| [Stockage](doc/fr/storage.md) | Les tables, ce qu'elles contiennent, et ce qu'elles ne contiennent jamais. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les ports à implémenter, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/newsletter-bundle
```

Avec Symfony Flex, la recette écrit ce qui suit. Sans elle :

```php
// config/bundles.php
Phil\NewsletterBundle\PhilNewsletterBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_newsletter.yaml
phil_newsletter:
    secret: '%env(NEWSLETTER_SECRET)%'
    sites:
        default: { base_url: 'https://example.com' }
    profiles:
        default: { from: 'news@example.com', from_name: 'Example' }
```

```yaml
# config/routes/phil_newsletter.yaml
phil_newsletter:
    resource: '@PhilNewsletterBundle/config/routes.php'
    prefix: /newsletter
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Twig 3.30 ou une version plus récente et
Doctrine DBAL 4.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
