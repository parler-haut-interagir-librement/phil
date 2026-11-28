<!-- phil-dev source-sha256=b0c8681bf079218f7cea960a30287c02b82610b5ed7f2c3b60c93526c661e238 -->
# Modèle de sécurité

## Ce qui est protégé

- Les sites : aucune requête n'atteint un applicateur ou un exécuteur à moins que la console l'ait signée pour ce
  site, maintenant, une seule fois.
- La vue que la console a des sites : aucune réponse n'est crue à moins que la clé du site l'ait signée pour cette
  requête.
- Les actions des opérateurs : lire et agir demandent des rôles que donne le fournisseur d'identité ; chaque action est
  journalisée.

## Clés

Chaque côté a une paire de clés Ed25519, générée par `phil:sites-console:keys` avec libsodium. La console garde sa clé
privée et la clé publique de chaque site ; chaque site garde sa clé privée et la clé publique de la console. Les clés
privées vivent dans l'environnement ou les secrets de chaque application et ne sont jamais écrites, journalisées ni
montrées par le bundle ; une `SigningKey` affiche `***` quand on la vide.

La console ne stocke ni mot de passe, ni jeton, ni clé privée d'aucun site : une clé publique vérifie un site, elle ne
peut pas agir comme lui. Une fuite de la base de données de la console ne permet à personne d'appeler un site. Une fuite
de la clé privée de la console permet à son détenteur d'envoyer des requêtes signées aux sites jusqu'à la rotation de
la clé, comme décrit plus bas.

## Rotation des clés

La clé de la console fait sa rotation sans couper aucun site : pendant une rotation, chaque agent accepte deux clés de
la console, la courante et la suivante. Une requête signée par l'une ou l'autre se vérifie ; toute autre clé est
refusée, et la fenêtre de dates, le nonce et le site lié à la signature sont vérifiés comme pour chaque requête.

Les clés que l'agent accepte viennent de sa configuration — `console_public_key` et, quand elle est donnée,
`console_next_public_key` — et d'un magasin, écrit seulement par l'action `keys` du protocole. Le magasin est un pool de
cache, `key_pool` ou à défaut `cache_pool`, qui doit être partagé par les serveurs du site et survivre aux
déploiements : Redis ou une base de données, pas un pool vidé par `cache:clear`. Il ne contient que des clés publiques,
avec l'empreinte des clés configurées dont il est parti : dès que les clés configurées changent, elles l'emportent de
nouveau sur le magasin, de sorte qu'un déploiement récupère toujours un site.

`phil:sites-console:keys:rotate` exécute la rotation depuis la console, en quatre étapes :

1. Elle lit la clé suivante, `console.next_private_key`. Sans elle, elle affiche une nouvelle paire à y mettre et
   s'arrête : la clé privée ne quitte jamais l'environnement ni les secrets de la console.
2. Elle dépose la clé publique suivante sur chaque site, l'un après l'autre, par une requête `keys` signée avec la clé
   courante. Un agent ne dépose une clé que sur une requête signée avec sa clé courante.
3. Dès que chaque site détient la clé suivante, la console signe avec elle. La clé qui signe est lue dans la ligne de la
   rotation avant chaque requête, de sorte que tous les processus de la console basculent ensemble.
4. Elle retire la clé précédente de chaque site par une requête `keys` signée avec la clé suivante. Un agent ne retire
   une clé que sur une requête signée avec la clé qui reste, et ne retire jamais son unique clé.

Chaque étape sur chaque site porte la même clé d'idempotence à chaque tentative, `<rotation>:<stage|retire>:<site>`, de
sorte qu'une étape livrée de nouveau n'est appliquée qu'une fois et répond son premier résultat. L'exécution s'arrête
au premier site en échec et affiche le rapport par site ; chaque étape est journalisée ; relancer la commande reprend
où elle s'était arrêtée. Un site retiré de la console pendant une rotation est sauté. Rien ne passe par une file de
Messenger, et seules des clés publiques quittent la console.

Quand la rotation est terminée, déplacez `console.next_private_key` vers `console.private_key` sur la console, et
donnez à `agent.console_public_key` la nouvelle clé publique sur chaque site à son prochain déploiement. D'ici là, un
site dont le magasin de clés est vidé revient à sa clé configurée, la précédente, et refuse la console : mettre à jour
sa configuration le rétablit.

### Quand une clé fuit

- **La clé privée de la console** : lancez une rotation tout de suite. Le détenteur de la clé qui a fuité peut encore
  envoyer des requêtes signées jusqu'à ce que chaque site l'ait retirée, et peut entre-temps déposer une clé à lui sur
  un site. Pour un site qui a pu être atteint, déployez sa configuration avec la nouvelle clé de la console comme
  `console_public_key` : une nouvelle configuration remplace ce que contient le magasin.
- **La clé privée d'un site** : son détenteur peut répondre à la console comme ce site. Générez une nouvelle paire avec
  `phil:sites-console:keys`, déployez-la comme `private_key` du site, et enregistrez la nouvelle clé publique avec
  `phil:sites-console:site:add`, qui met à jour un site connu.
- **La clé privée suivante, pendant une rotation** : recommencez avec une autre clé suivante. Une rotation en cours
  refuse une autre clé suivante ; une fois qu'elle est terminée, lancez une nouvelle rotation vers une clé neuve.

## Requêtes

La console signe, avec sa clé privée :

```text
phil-sites-console/1 request
<site>
<action>
<date, Unix seconds>
<nonce>
<SHA-256 of the body, hex>
```

et envoie le site, la date, le nonce et la signature dans les en-têtes `Phil-Console-Site`, `Phil-Console-Date`,
`Phil-Console-Nonce` et `Phil-Console-Signature`. L'agent refuse la requête, avec `401` et une ligne dans ses journaux,
quand :

- un en-tête manque ;
- le site n'est pas son propre `site_id` : une requête pour un site ne peut pas être rejouée vers un autre ;
- la date s'écarte de plus de `max_clock_skew` secondes — 300 par défaut — de son horloge ;
- le nonce n'est pas de 22 à 64 caractères en base64url ;
- la signature ne se vérifie avec aucune des clés de la console que le site accepte, sur cette action et ce corps ;
- le nonce a déjà été accepté dans les deux fois l'écart.

Le nonce n'est retenu qu'après la vérification de la signature, pour que personne sans la clé ne puisse remplir le
dépôt. Le dépôt est le pool de cache du site, et la vérification et l'écriture d'un nonce se font sous un verrou de
`agent.lock_factory` : deux copies d'une même requête arrivant en même temps passent une fois, l'autre est un rejeu. Sur
plusieurs serveurs, le pool et le magasin de verrous doivent être ceux qu'ils partagent. Un nonce que le pool n'enregistre
pas n'est jamais accepté : la requête reçoit la réponse `503`, non signée, et la console peut la renvoyer.

Le corps est lu jusqu'à un octet au-delà de 64 Kio, sans plus, avant la vérification de la signature : un
`Content-Length` au-dessus du plafond est refusé sans lecture, et un corps plus long que ne le dit son en-tête est
refusé une fois le plafond dépassé.

## Réponses

Chaque réponse à une requête vérifiée est signée par le site sur :

```text
phil-sites-console/1 response
<site>
<nonce of the request>
<status>
<SHA-256 of the body, hex>
```

dans l'en-tête `Phil-Agent-Signature`. La console refuse une réponse qui en est dépourvue, signée par une autre clé, ou
signée pour une autre requête ; une réponse d'un site pour un autre site est refusée aussi. Elle ne suit aucune
redirection, s'arrête après sa limite de temps et refuse une réponse de plus de 1 Mio. Chaque document qu'elle lit est
vérifié membre par membre : tailles, profondeur, noms de paquets, versions, états.

## Transport

Les requêtes sont signées, non chiffrées. Les agents ne sont joints qu'en `https`, sauf si `allow_insecure_urls` est
activée pour le développement. L'adresse d'un site est lue par `phil/url`, sur un nom d'hôte : une adresse IP est
toujours refusée, et `localhost` aussi à moins que cette option soit activée. Seul un opérateur peut enregistrer un
site, depuis la ligne de commande.

## Idempotence

Une poussée porte son identifiant, l'étape d'un plan le plan et le site, et l'étape d'une rotation de clé la rotation,
l'opération et le site ; l'agent exécute chacun une fois par `phil/idempotency`, avec pour clé la portée et l'identifiant, et pour empreinte le SHA-256 du corps. Une nouvelle
tentative reçoit le premier résultat ; un identifiant réutilisé avec un autre corps est refusé. Les clés sont gardées
sept jours ; une exécution en cours garde sa clé quinze minutes.

## Qui peut lire et agir

Les écrans prennent deux rôles du jeton de l'utilisateur : le rôle de lecteur pour lire, le rôle d'opérateur pour
interroger, livrer et exécuter. Le bundle n'a pas de comptes à lui : le pare-feu de l'application authentifie
l'utilisateur auprès du fournisseur d'identité et associe ses revendications à des rôles. Chaque action est un POST
avec un jeton CSRF, et est journalisée avec l'identifiant d'utilisateur. Les commandes sont pour les opérateurs du
serveur de la console ; leur `--actor` les nomme dans le journal.

## Messages

Les messages du bundle — `PullSite`, `DeliverConfigurationPush`, `RunUpdatePlan` — portent des identifiants seulement :
un site, une poussée, un plan, un nombre de lots, l'identifiant d'utilisateur de l'opérateur. Les réglages restent dans
la base de données. Une rotation de clé s'exécute depuis sa commande, sans aucun message.

## Ce que le bundle ne fait jamais

- Déployer, héberger ou approvisionner un site, ou exécuter Composer sur un serveur.
- Garder un identifiant de connexion d'un site, ou de ses administrateurs.
- Pousser quoi que ce soit sans signature et sans ligne dans le journal.
- Suivre une redirection, ou appeler une adresse qu'un opérateur n'a pas enregistrée.

## Points ouverts

- Une revue externe du protocole et de son implémentation, exigée avant une version bêta.
