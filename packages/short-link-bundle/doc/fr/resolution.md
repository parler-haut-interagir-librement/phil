<!-- phil-dev source-sha256=2e56352d771f79013b57af1c5add07fadbd768b60bcf1a7bff9b0726a00f746c -->
# Résolution

`Infrastructure\Http\ShortLinkListener` répond aux requêtes sur les domaines de liens courts à `kernel.request`, avec
une priorité de 40, avant le routage. Une requête sur tout autre hôte suit son cours sans être touchée, et sans
requête à la base.

## Ce que répond une requête

| Requête | Réponse |
|---|---|
| Une méthode autre que `GET` ou `HEAD` | `405 Method Not Allowed`, avant que le lien ne soit lu |
| La racine d'un domaine | Un `302` vers `home_url`, ou un `404` |
| Un chemin qui n'est pas un code : un deuxième segment, un caractère hors de l'alphabet, plus de 64 caractères | `404`, sans requête à la base |
| Un code que personne ne détient, un lien d'une autre locale, un lien pas encore en vigueur | `404` |
| Un lien désactivé, après sa fin ou sans visites restantes | `410 Gone` |
| Un lien en vigueur | Son statut de redirection — `302` par défaut, ou `301`, `307`, `308` — vers sa cible |

Une barre oblique finale est ignorée. Le `404` et le `410` sont lancés comme des exceptions HTTP, de sorte que les pages
d'erreur de l'application les produisent. Un `GET` et un `HEAD` comptent chacun une visite, celle d'une personne ou celle d'une machine, voir plus bas.

La redirection porte `Cache-Control: no-store, private`, pour qu'aucun navigateur ni cache partagé ne la garde et que
chaque visite atteigne le compte, `X-Robots-Tag: noindex` et `Referrer-Policy: no-referrer`, pour que la cible
n'apprenne pas l'adresse courte qui lui a envoyé le visiteur. Un statut permanent (`301`, `308`) demande aux
navigateurs de retenir la cible : un navigateur qui le fait y va sans passer par le domaine court, et ses visites
suivantes ne sont pas comptées.

## Le résolveur de phil/redirects-bundle

`Application\ShortLinkResolver` transforme le lien en règle de redirection de phil/redirects-bundle — une source exacte
sur le domaine, la cible du lien, son statut et sa politique de chaîne de requête — et le `LocationBuilder` des
redirections écrit l'emplacement. La chaîne de requête de la visite est abandonnée, ou ajoutée après les paramètres de
la cible quand le lien la garde ; l'emplacement est vérifié une fois de plus et envoyé sous sa forme canonique. Un
emplacement qui ne peut pas être construit est introuvable, et aucune visite n'est comptée.

## Les domaines des locales

Un lien sans locale est servi sur tous les domaines. Un lien avec une locale est servi sur les domaines de cette
locale et sur les domaines sans locale, et il est introuvable sur le domaine d'une autre locale. Son adresse,
`ShortLinks::url()`, s'écrit sur le premier domaine de sa locale, ou à défaut sur le premier domaine sans locale, ou
à défaut sur le premier domaine.

## Le compte des visites

Une visite est comptée par un seul `UPDATE` dont les conditions sont les conditions du lien à ce moment : activé, à
partir de son début, avant sa fin, sous son maximum de visites de personnes. Une visite de personne s'ajoute à `visits` et fixe `last_visit_at` ; celle
d'une machine s'ajoute seulement à `robot_visits`. Deux requêtes en course pour la dernière visite sont comptées l'une
après l'autre par la base de données : l'une redirige, l'autre répond `410 Gone`. Le compte est synchrone ; les enregistreurs
sont informés une fois la réponse partie, à `kernel.terminate`, et un enregistreur qui échoue est journalisé sans faire
échouer les autres. L'écouteur abandonne la visite d'une requête à `kernel.reset`, de sorte qu'un *worker* ne garde
rien d'une requête à la suivante.

## Les personnes et les machines

Bien des requêtes sur un lien court ne viennent d'aucune personne : le robot d'un moteur de recherche, un moniteur, un
script, un navigateur qui charge le lien d'avance pour un clic qui ne viendra peut-être jamais, un service de messagerie
qui dessine l'aperçu du lien dès qu'il est publié. Chaque visite est classée avant que le lien ne soit lu, et seules les
visites de personnes comptent :

| Classe | Reconnue par | Comptée dans |
|---|---|---|
| `Human` | Rien de ce qui suit ne correspond | `visits()`, que lit le maximum, et `lastVisitAt()` |
| `Prefetch` | Un en-tête d'intention — `Sec-Purpose`, `Purpose`, `X-Purpose`, `X-Moz` — qui contient le jeton `prefetch`, `prerender` ou `preview` | `robotVisits()` |
| `LinkPreview` | Un `User-Agent` qui désigne l'aperçu d'un service de messagerie ou d'un réseau social : Slack, Discord, Telegram, WhatsApp, Teams, Facebook et iMessage, Mastodon… | `robotVisits()` |
| `Robot` | Aucun `User-Agent`, ou un qui désigne un robot d'indexation, un script, un moniteur ou un navigateur sans interface : `bot`, `crawl`, `spider`, `curl/`, `python`… | `robotVisits()` |

- **Une machine est redirigée de la même façon** qu'une personne, pour qu'un aperçu montre la cible et qu'une prélecture
  soit prête quand le clic vient. Elle n'est comptée que tant que le lien est en vigueur, par la même instruction, et
  n'entame jamais le maximum : un lien limité à une visite laisse passer tous les aperçus et reste pour la seule
  personne qui clique.
- **L'ordre** : l'intention d'abord, puis les aperçus de liens — leurs noms contiennent souvent `bot` —, puis les robots.
- **Seule la classe va plus loin.** Les en-têtes sont lus puis abandonnés : ni le `User-Agent` ni l'adresse ne sont
  conservés, journalisés ou donnés aux enregistreurs. `ShortLinkVisit::$visitor` porte la classe.
- **Borné** : les 512 premiers octets du `User-Agent` et les 256 premiers des en-têtes d'intention sont lus, comparés par
  simple recherche sans égard à la casse, jamais par une expression régulière construite à partir de la requête.
- **Un client qui ment est compté pour ce qu'il prétend être.** Un robot qui envoie le `User-Agent` d'un navigateur compte
  comme une personne ; la classification sépare les machines honnêtes, qui sont la plupart, pas les hostiles.
- **Les listes s'allongent** avec `visitors.link_previews` et `visitors.robots` ([Configuration](configuration.md)) ; la
  classification entière se remplace par `Application\Contract\VisitorClassifier`
  ([Points d'extension](extension-points.md)).

## L'ordre des conditions

Un lien est désactivé avant toute autre chose, puis après sa fin, puis sans visites restantes, puis pas encore commencé :
un lien à la fois sans visites restantes et pas encore commencé répond `410 Gone`.
