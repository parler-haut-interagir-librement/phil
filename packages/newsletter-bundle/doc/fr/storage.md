<!-- phil-dev source-sha256=6ed5caecd02da6eb1d3c3277d81c810768d930a798cb6917fe45d36269b319aa -->
# Stockage

Le bundle conserve ses données dans des tables d'une connexion Doctrine DBAL, sur PostgreSQL, MySQL, MariaDB ou SQLite.
Les instants sont des horodatages Unix.

| Table | Contient |
|---|---|
| `phil_newsletter_list` | Les listes, par site et clé |
| `phil_newsletter_subscriber` | Les abonnés : adresse, identifiant externe, état, dates, rebonds souples |
| `phil_newsletter_membership` | La place de chaque abonné sur chaque liste |
| `phil_newsletter_campaign` | Les campagnes |
| `phil_newsletter_link` | Les liens que suit chaque campagne, par indice |
| `phil_newsletter_delivery` | Une ligne par message à un abonné |
| `phil_newsletter_template` | Les gabarits des administrateurs et des membres ; un gabarit partagé a un site vide |
| `phil_newsletter_click` | Les clics de chaque lien, dans un total |
| `phil_newsletter_recipient_click` | Les clics par destinataire, pour les campagnes qui l'ont choisi |
| `phil_newsletter_unsubscribe_reason` | Les raisons de partir, un total par site, liste, raison et jour |
| `phil_newsletter_suppression` | Les adresses supprimées, sous forme d'empreintes à clé |

```php
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;

$schema = NewsletterSchema::configure($schema);
```

`configure()` ajoute les tables qu'un schéma n'a pas. Avec DoctrineBundle et l'ORM, `NewsletterSchemaListener` les
ajoute au schéma que l'ORM génère pour la connexion du bundle.

Un envoi est créé par une insertion qui laisse en paix une ligne du même identifiant — `ON CONFLICT DO NOTHING` sur
PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite —, de sorte qu'une campagne met en file
un message par abonné. Un compte de clics ou de raisons est l'insertion d'une ligne à zéro si elle n'y est pas, puis un
seul `UPDATE` qui en ajoute un, pour qu'aucun clic ne soit perdu au profit d'un autre au même instant.

Les tables ne contiennent ni adresse IP ni agent utilisateur ; les raisons de partir et les clics dans un total ne
nomment personne.
