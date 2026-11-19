<!-- phil-dev source-sha256=de233b43a37df5e071afa03ebe50e281cc2553f4106b2a90209bd47d22826fe3 -->
# Sécurité et vie privée

## Les cibles

Un domaine court qui redirige n'importe où est une redirection ouverte : quiconque détient un lien envoie les visiteurs
où le lien le dit, sous la réputation du domaine. Chaque cible est donc vérifiée à l'enregistrement d'un lien :

- une URL `http` ou `https` absolue, lue par phil/url comme la lit un navigateur : aucune adresse IP, sous aucune de ses
  graphies, aucun nom simple, aucun nom d'utilisateur ni mot de passe, aucun `localhost` à moins que `allow_localhost`
  ne soit activé ; elle est gardée sous sa forme canonique, un hôte international en ASCII ;
- jamais sur un domaine de lien court, pour qu'aucun lien n'en envoie un autre ;
- sur un hôte de `targets.allowed_hosts`, un nom d'hôte exact — un sous-domaine est un autre hôte —, à moins que
  `targets.any_host` n'autorise n'importe quel hôte. La liste est vide par défaut, pour qu'aucun lien ne parte vers un
  hôte que l'application n'a pas nommé ;
- avec `targets.check`, lue par phil/safe-http : une adresse qui est ou se résout vers un réseau privé, une adresse qui
  redirige vers un tel réseau, une qui ne répond pas à temps ou qui répond par un statut d'erreur est refusée.

L'emplacement envoyé à chaque visite est écrit et vérifié de nouveau par le constructeur d'emplacements de
phil/redirects-bundle.

## La limite de débit

Créer un lien dépense une création de l'allocation de son auteur : 30 par heure par défaut, sur une fenêtre glissante.
L'auteur est le compte connecté dans l'administration, ou à défaut l'adresse du client ; elle est hachée avant de
nommer la fenêtre, de sorte que le magasin du limiteur ne conserve ni nom de compte ni adresse. L'allocation n'est
dépensée qu'une fois le lien valide par ailleurs : une cible refusée ne dépense rien. Dans l'environnement `prod`, un
groupe de cache propre à chaque serveur est refusé, puisqu'un client pourrait dépenser la limite une fois par serveur.

## L'administration

Chaque page a besoin du rôle configuré ; chaque bouton poste un jeton CSRF, et le formulaire porte le jeton des
formulaires de Symfony. La liste ne signale un résultat que parmi une liste fermée, jamais ce qu'un lien trafiqué met
dans sa chaîne de requête. Le code QR est servi avec `Content-Security-Policy: default-src 'none'` et
`X-Content-Type-Options: nosniff`.

## Ce qui est conservé

Un lien garde son code, sa cible, son libellé, sa locale, ses conditions, le compte de ses visites de personnes, le
moment de la dernière et le compte de ses visites de machines. Rien du visiteur : ni adresse, ni agent utilisateur, ni
référent. Le `User-Agent` et les en-têtes d'intention sont lus pour classer la visite, puis abandonnés. Les
enregistreurs informés d'une visite reçoivent un `ShortLinkVisit` — l'identifiant du lien, son code, l'hôte, sa locale
et la classe de son visiteur — et rien d'autre. L'enregistreur de la mesure d'audience ne compte que les visites de
personnes, comme événement serveur de phil/analytics-bundle selon les règles propres à ce bundle : le code et la locale ne deviennent des compteurs que si le site les déclare comme dimensions avec ces
valeurs.

## Les bornes

Chaque entrée est bornée : un code de 64 caractères, une cible de 2 048, un libellé de 200, un identifiant décodé de
255, une recherche de 200, un numéro de page de 100 000. Le décodage du codec a une cible de fuzzing.
