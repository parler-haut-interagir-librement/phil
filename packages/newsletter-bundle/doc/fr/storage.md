<!-- phil-dev source-sha256=bd52dd078cb677ae38fc4c9ccca88b19a0b6c03e441075768f3220ef6c21d2be -->
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

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Une adresse ne tient pas compte de la casse par sa clé
: `Subscriber::keyOf()` la rogne et la met en minuscules avant de la stocker ou de la chercher, pour que
`Ann@Example.com` et `ann@example.com` soient un seul abonné. Les accents sont une autre affaire : `muller@example.com`
et `müller@example.com` sont deux boîtes aux lettres, et une collation qui ignore les accents n'en ferait qu'une. Les
sites, les clés et les identifiants sont comparés tels qu'ils sont écrits : le site `Acme` n'est pas `acme`.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_newsletter_list` | `id`, `site`, `list_key` |
| `phil_newsletter_subscriber` | `id`, `site`, `email_key`, `external_id` |
| `phil_newsletter_membership` | `subscriber_id`, `list_id` |
| `phil_newsletter_campaign` | `id`, `site`, `list_id` |
| `phil_newsletter_link` | `campaign_id`, `url_hash` |
| `phil_newsletter_delivery` | `id`, `subscriber_id`, `campaign_id` |
| `phil_newsletter_template` | `site`, `template_key` |
| `phil_newsletter_click` | `campaign_id` |
| `phil_newsletter_recipient_click` | `subscriber_id`, `campaign_id` |
| `phil_newsletter_unsubscribe_reason` | `site`, `list_id` |
| `phil_newsletter_suppression` | `site`, `email_hash` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.

Un envoi est créé par une insertion qui laisse en paix une ligne du même identifiant — `ON CONFLICT DO NOTHING` sur
PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite —, de sorte qu'une campagne met en file
un message par abonné. Un compte de clics ou de raisons est l'insertion d'une ligne à zéro si elle n'y est pas, puis un
seul `UPDATE` qui en ajoute un, pour qu'aucun clic ne soit perdu au profit d'un autre au même instant.

Les tables ne contiennent ni adresse IP ni agent utilisateur ; les raisons de partir et les clics dans un total ne
nomment personne.
