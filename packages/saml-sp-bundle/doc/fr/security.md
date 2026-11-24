<!-- phil-dev source-sha256=64c2463117f0b484c7c305be698be3c0f247ad82f76a14b823aa1f88be2981ed -->
# Sécurité

Chaque contrôle de phil/saml-sp s'exécute sur chaque réponse : voyez son guide de sécurité pour les attaques XML, les
signatures, l'enveloppement de signature, les contrôles du profil, le chiffrement et le rejeu. Le bundle ajoute ce dont
une application a besoin autour d'eux.

## La requête à laquelle on répond

L'ID de chaque requête est gardé dans la réserve de cache pendant `request_lifetime`, dix minutes par défaut, et voyage
comme état de relais ; la réponse revient avec lui. Une réponse doit répondre à cette requête — dans `InResponseTo`, dans
la confirmation de son assertion — pour le fournisseur d'identité à qui elle a été envoyée, une seule fois : une requête
est oubliée dès qu'on y répond.

## Le navigateur

Avec `bind_to_browser` actif, le choix par défaut, la connexion pose un témoin, `phil_saml_sp_binding`, qui contient un
jeton aléatoire dont l'empreinte est gardée avec la requête ; la réponse doit revenir avec ce témoin. Une réponse
capturée depuis un autre navigateur, ou une connexion lancée par un autre site dans le navigateur de la personne et
terminée ailleurs, est refusée. Le témoin est `HttpOnly`, `Secure` et `SameSite=None` — la réponse est postée par la page
du fournisseur d'identité, un `POST` intersite qu'un témoin `Lax` ne suivrait pas —, de portée `/`, et effacé une fois la
personne connectée. Un navigateur ne garde un témoin `SameSite=None` que sur HTTPS : en HTTP simple, en développement, la
recette définit `secure_cookie: false` et `bind_to_browser: false`, et la connexion n'y est liée à aucun navigateur.

## Où vont les gens

La `target` de la connexion et l'état de relais d'une réponse non sollicitée ne sont suivis que s'ils sont un chemin de
l'application : un seul `/` au début, aucun `//`, aucune barre oblique inverse, aucun caractère de contrôle, 2048 octets
au plus. Tout le reste va à `default_target_path` : aucune redirection vers un autre site.

## Réponses non sollicitées

Une réponse qui ne répond à aucune requête, qu'un fournisseur d'identité envoie quand une personne ouvre l'application
depuis son portail, est refusée sauf si `allow_unsolicited` est défini pour ce fournisseur d'identité. Une telle réponse
n'est liée à aucun navigateur : une réponse capturée en transit pourrait connecter son détenteur dans les minutes où elle
est valide, et une page pourrait faire connecter une personne à un compte de l'attaquant. Ne la permettez que pour une
organisation dont le portail l'exige, et gardez le décalage d'horloge et `max_age` courts.

## Temps

L'horloge est le service `clock`. `clock_skew`, deux minutes par défaut, élargit chaque contrôle de temps ; `max_age`,
dix minutes, borne l'âge d'une réponse. Gardez les serveurs synchronisés : un décalage plus grand que le réglage refuse
chaque connexion.

## Rejeu

Une assertion est acceptée une seule fois. Avec une connexion Doctrine DBAL, le bundle garde les assertions acceptées
dans la table de `DbalReplayGuard` : la première utilisation d'une assertion est un seul `INSERT` sur la clé primaire,
de sorte que de deux serveurs qui reçoivent la même assertion au même moment, la base de données en laisse un l'insérer
et refuse l'autre. Sans connexion, `CacheReplayGuard` les garde dans `cache_pool` ; il lit, puis écrit : deux copies
postées au même instant à deux serveurs pourraient passer toutes les deux, et la réserve doit être partagée par chaque
serveur.

Une réserve de cache locale à chaque serveur — APCu, un tableau, des fichiers, la réserve système, et `cache.app`, qui
est sur le système de fichiers par défaut — ne retient une assertion que sur le serveur qui l'a acceptée : un attaquant
qui a capturé une réponse la poste de nouveau à un autre serveur derrière le répartiteur de charge, qui ne l'a jamais
vue et l'accepte. Donc dans l'environnement `prod`, le conteneur ne se construit pas quand les assertions acceptées
seraient gardées dans une telle réserve, et il dit quoi faire :

- configurer une connexion Doctrine DBAL, que le bundle prend alors, et créer la table (voir
  [Installation](installation.md#la-table-des-assertions-acceptées)) ;
- ou nommer un `cache_pool` sur un magasin partagé — Redis, Memcached, une base de données ;
- ou, sur un seul serveur, mettre `single_server: true`.

La vérification lit les définitions de services — l'adaptateur parent de la réserve, ou sa classe —, suit les alias et
les parents, et tient toute autre réserve pour partagée. Les autres environnements ne sont pas vérifiés.

## Le cache

La réserve contient les requêtes en attente et les métadonnées récupérées, et sans base de données les ID des
assertions acceptées. Elle est partagée par chaque serveur de l'application, de sorte qu'une réponse qui atteint un
autre serveur soit encore contrôlée par rapport à sa requête ; et elle n'est pas vidée au déploiement, sans quoi une
connexion en cours, ou une assertion encore valide, serait oubliée. Une réserve à part, sur un magasin partagé, est plus
claire.

## Réponses

Une réponse refusée répond 403 avec une phrase ; un message de déconnexion refusé, 400 ; un fournisseur d'identité hors
d'atteinte à la connexion, 503. Aucune ne porte la raison, qui va à `SamlResponseRefused`. La page de la liaison
HTTP-POST n'exécute aucun script en ligne, fonctionne sous une Content Security Policy stricte, et n'est jamais mise en
cache ; les redirections non plus.

## Clés

Les clés privées sont lues dans l'environnement, en PEM, à la première requête qui en a besoin, et jamais écrites dans le
cache du conteneur. `phil:saml:check` avertit avant qu'un certificat n'expire. Le fournisseur de service ne signe jamais
avec SHA-1.

## Workers

Les services ne gardent rien d'une requête : les paires de clés et les métadonnées chargées sont oubliées entre les
requêtes d'un *worker* par `kernel.reset`, et le client HTTP de phil/safe-http oublie les adresses qu'il a résolues.
