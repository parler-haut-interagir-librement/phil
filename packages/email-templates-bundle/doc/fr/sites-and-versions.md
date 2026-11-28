<!-- phil-dev source-sha256=76614114d125cebd9b78fb8c62b509ba6522f9ecab0b31de5a7b979511bfd16e -->
# Sites, brouillons et versions

## Les sites et la portée partagée

Un gabarit appartient à un **site** — 1 à 64 lettres ASCII, chiffres, `_`, `.`, `:` ou `-`, l'identifiant d'un
locataire, d'un domaine ou d'une marque — ou à la **portée partagée**, le site vide, que tout site lit quand il ne
publie rien de lui-même. Le site courant est celui du `SiteScope` :

| Configuration | Site courant |
|---|---|
| `phil/tenancy-bundle` activé, `site` non défini | le locataire actif de son contexte ; hors de tout locataire, la portée partagée (`TenancySiteScope`) |
| `site: main` | `main` (`FixedSiteScope`) |
| ni l'un ni l'autre | la portée partagée |

Le site est une colonne du gabarit, non un filtre de `phil/tenancy-bundle` : la portée partagée n'appartient à aucun
locataire, et un filtre qui masque chaque ligne d'un autre locataire la masquerait aussi. Chaque recherche nomme le site
qu'elle lit.

## Brouillons, publication et versions

Chaque site conserve, par gabarit et par locale, un **brouillon** — la copie de travail que ses rédacteurs modifient — et
les **versions** qu'il a publiées. L'enregistrement écrit le brouillon ; rien de ce qu'un site enregistre n'est envoyé
avant d'être **publié**. La publication fige la version à laquelle le brouillon appartient, et l'enregistrement suivant
commence la version d'après : une version publiée n'est donc jamais modifiée, et les versions publiées forment
l'historique.

| État du brouillon (`PublicationState`) | Signifie |
|---|---|
| `Unpublished` (`unpublished`) | jamais publié, ou mis hors ligne : le site envoie le gabarit partagé ou le fichier par défaut |
| `Pending` (`draft`) | publié, avec des changements que le site n'envoie pas encore |
| `Published` (`published`) | le site envoie exactement ce brouillon |

`TemplateDraft` contient la définition, la version à laquelle il appartient, sa **révision** — le nombre de fois qu'il a
été enregistré —, son état, le moment et l'auteur du dernier enregistrement, et `liveVersion`, la version que le site
envoie, `null` tant qu'il n'y en a pas. `TemplateVersion` contient une version publiée : sa définition, son numéro, le
moment et l'auteur de son enregistrement, et `publishedAt`.

## Quel gabarit est envoyé

Les locales sont essayées tour à tour — celle qui est demandée, sa langue, la locale par défaut, sa langue — et, pour
chacune, la version que le site a publiée, puis celle que la portée partagée a publiée, puis le fichier par défaut.
Ainsi, `fr_CA` publié dans la portée partagée l'emporte sur `fr` publié par le site, et un site qui a publié `fr` l'envoie
pour `fr_CA` quand rien n'est publié pour `fr_CA`. Un brouillon n'est jamais envoyé : tant qu'il n'est pas publié, c'est
l'étape suivante de la chaîne qui répond. Une version demandée par son numéro est celle que le site — ou la portée
partagée — a publiée exactement dans cette locale.

## Gérer les gabarits

`Application\EmailTemplates` gère les gabarits des sites :

| Méthode | Fait |
|---|---|
| `save($key, $locale, TemplateDefinition $definition, int $expected, ?Site $site, ?string $author)` | Vérifie le gabarit, puis enregistre le brouillon, dans une nouvelle version quand la dernière est publiée ; `$expected` est la révision à partir de laquelle il a été modifié, `0` pour un gabarit que le site n'a jamais enregistré |
| `publish($key, $locale, ?Site $site, ?string $author)` | Vérifie de nouveau le brouillon, puis le publie : le site l'envoie dès lors |
| `revert($key, $locale, int $version, int $expected, ?Site $site, ?string $author)` | Copie une version publiée dans le brouillon, une fois qu'elle passe de nouveau les vérifications ; elle est envoyée une fois publiée |
| `discard($key, $locale, ?Site $site, ?string $author)` | Abandonne les changements faits depuis la publication de la locale : le brouillon redevient la version publiée |
| `reset($key, $locale, ?Site $site, ?string $author)` | Met la locale hors ligne : le site envoie de nouveau le gabarit partagé, ou le fichier par défaut ; le brouillon et les versions sont conservés |
| `current($key, $locale, ?Site $site)` | Le `ResolvedTemplate` que le site envoie, et d'où il vient |
| `draft($key, $locale, ?Site $site)` | Le `TemplateDraft` que le site conserve exactement dans cette locale, `null` s'il n'en a jamais enregistré |
| `history($key, $locale, ?Site $site, int $limit = 50)` | Les versions publiées, la plus récente d'abord |
| `catalog(?Site $site)` | Chaque gabarit que le site envoie ou dont il conserve un brouillon, dans chaque locale, par clé : d'où vient ce qu'il envoie, la version envoyée et l'état du brouillon propre au site |

`$site` est le site courant quand il vaut `null`. Les vérifications d'un enregistrement et d'une publication sont celles
de `TemplateValidator` : les sources se compilent dans le bac à sable et ne lisent que ce que le schéma déclare, puis
chaque variante — aucun segment, chaque segment — produit son rendu avec les valeurs d'exemple, mise en page, styles en
ligne et images intégrées compris. `InvalidTemplate` liste chaque raison ; `VersionConflict` indique qu'une autre
révision a été enregistrée entre-temps ; `UnknownTemplate` indique qu'il n'y a pas de brouillon à publier, ou pas de
version publiée de ce numéro. Une clé qu'aucun fichier par défaut ne déclare est refusée. `discard()` et `reset()`
retournent s'il y avait quelque chose à faire, et n'annoncent rien sinon.

L'auteur est l'identifiant de qui a enregistré, conservé avec le brouillon et dans les événements : 1 à 128 lettres
ASCII, chiffres, `_`, `.`, `:` ou `-`. Tout le reste — une adresse courriel surtout — n'est pas conservé.

## Les événements et le journal d'audit

Chaque changement distribue, par le répartiteur PSR-14 s'il y en a un, un événement de `Domain\Event` — des
identifiants seulement, jamais une source :

| Événement | Champs |
|---|---|
| `TemplateSaved` | clé, site, locale, version, révision, auteur, et la version copiée par `revert()` |
| `TemplatePublished` | clé, site, locale, version publiée, auteur |
| `TemplateDraftDiscarded` | clé, site, locale, version du brouillon, auteur |
| `TemplateReset` | clé, site, locale, auteur |

Avec `phil/audit-bundle` activé, `AuditTemplateChanges` consigne chacun dans le journal, dans le segment
`audit.segment`, sous le nom `email_templates.saved`, `email_templates.reverted`, `email_templates.published`,
`email_templates.discarded` ou `email_templates.reset`, le gabarit en étant le sujet et le site, le locataire.

## Les contenus

Avec le magasin par défaut, `content`, les gabarits sont des contenus de `phil/content-bundle` (`ContentTemplateStore`) :

- **une racine par gabarit, portée et locale**, `Infrastructure\Content\Entity\EmailTemplateContent`, dans la table
  `phil_email_template_content` : son identifiant, un UUIDv7, et son site — le texte vide pour la portée partagée —, sa
  clé et sa locale, uniques ensemble et comparés octet par octet (`utf8mb4_bin` sur MySQL et MariaDB, par
  `TemplateKeysCollation` ; les collations déterministes par défaut de PostgreSQL et de SQLite) ;
- **ses lignes**, `EmailTemplateDimension`, dans la table `phil_email_template_dimension`, une par étape et par version,
  dans la locale de la racine, et la ligne non localisée de chacune, qu'un gabarit laisse vide. L'étape de brouillon est
  la copie de travail, l'étape en ligne ce que le site envoie. La ligne est `TemplateCapable`, ses valeurs de champs
  contenant la définition, la révision, le moment de l'enregistrement et l'auteur (`TemplatePayload`), et
  `WorkflowCapable`, la place du flux de travail éditorial et la date de publication.

Les écritures passent par `ContentManager` : `edit()` enregistre le brouillon et crée une fourche d'une version publiée,
`publish()`, `unpublish()` et `removeDraft()` publient, mettent hors ligne et abandonnent les changements, chacun dans
une transaction de `ContentTransaction`, avec le flux de travail de `phil/content-bundle`, ses gardes et ses événements.
Un enregistrement verrouille la racine du gabarit, si bien que deux enregistrements à partir de la même révision ne
peuvent passer tous deux la vérification ; deux premiers enregistrements d'un même gabarit à la fois créent la même
racine, et la clé unique refuse le second comme un `VersionConflict`.

Une racine contient une seule locale parce que les locales d'un gabarit s'écrivent et se publient séparément :
`phil/content-bundle` crée la fourche de chaque locale d'une version ensemble et remet chaque locale publiée à
`draft`, si bien qu'une seule racine pour toutes les locales montrerait le gabarit français modifié chaque fois que
l'anglais est enregistré.

Les locales dans lesquelles un site écrit des gabarits font partie de celles que sert `phil_content.locales` ; une locale
qu'il ne sert pas, ou que les contenus ne peuvent contenir — une écriture comme `zh_Hant` —, est un `InvalidTemplate`.
Le repli d'une locale à la suivante est celui du bundle, comme ci-dessus, non les chaînes de `phil_content.locales`.

`phil/content-bundle` liste chaque racine déclarée dans sa propre administration et dans son flux de recherche : le genre
`phil_email_template` y apparaît à côté des pages. Ses lignes n'ont ni route, ni corps, ni titre : elles ne sont donc
jamais servies comme des pages, et le flux ne leur donne aucun texte.

## En mémoire

`InMemoryTemplateStore` conserve les brouillons et les versions pour la durée de vie du processus, avec les mêmes
règles — une version publiée figée, l'enregistrement suivant dans la version d'après, les changements abandonnés
ramenés à la version publiée —, pour les tests et pour une application sans base de données. Ses publications sont
datées par l'horloge configurée.
