<!-- phil-dev source-sha256=078a1e1cd5835efef64abc1deac2e8f43056d2e4487f015b76720a1d82fa26cb -->
# Portée

Les abonnements pour le noyau du commerce : des offres en paliers d'une famille, des périodes de facturation, des
essais, le renouvellement par une commande du noyau, la relance après un paiement échoué, l'annulation à la fin de la
période payée, les changements de palier au renouvellement suivant ou sur-le-champ au prorata, les cadeaux, et les
fonctionnalités que débloque chaque palier — à un abonné, ou aux membres d'une organisation — des paliers gratuits et
payants, ou des paliers de soutien, dans un seul modèle.

| Partie | Contenu | État |
|---|---|---|
| Offres et paliers | Les offres d'une famille classées en paliers, vendues par le `SellableResolver` du noyau sous le type de référence `offer` | Implémenté et testé ([offers.md](offers.md)) |
| Périodes et essais | Les périodes comptées à partir d'un point d'ancrage, un essai avant le premier prélèvement | Implémenté et testé ([subscriptions.md](subscriptions.md)) |
| Renouvellement et relance | Une commande du noyau par période, les nouvelles tentatives d'un paiement échoué, en souffrance puis terminé, l'annulation à la fin de la période payée ; des étapes de relance par offre qui gardent toutes les fonctionnalités, quelques-unes ou aucune après un certain nombre de jours | Implémenté et testé ([dunning.md](dunning.md)) |
| Changements de palier | Dans la famille, au renouvellement suivant | Implémenté et testé |
| Changements immédiats avec calcul au prorata | Un changement qui prend effet sur-le-champ, la partie inutilisée de la période créditée, le crédit restant reporté aux commandes suivantes, comme `OrderProcessor` ; un palier inférieur au renouvellement suivant, sauf configuration | Implémenté et testé ([proration.md](proration.md)) |
| Cadeaux | Un acheteur paie, un code s'échange, le bénéficiaire désigné par un identifiant stable ; renouvelé aux frais de l'acheteur ou terminé à son terme | Implémenté et testé ([gifts.md](gifts.md)) |
| Organisations | Un abonnement qui ouvre ses fonctionnalités à chaque membre d'une organisation, par un port de l'application, avec des places facultatives | Implémenté et testé ([organizations.md](organizations.md)) |
| Fonctionnalités par palier | `FeatureAccess`, lu par l'application pour ouvrir ou fermer une fonctionnalité | Implémenté et testé ([features.md](features.md)) |

## Ce qu'il laisse à d'autres paquets

- **Les droits sur le contenu** — quelle page, quel fichier ou quel épisode un abonné peut lire — relèvent de
  `phil/content-access`, qui lit les fonctionnalités que ce module accorde.
- **Les avantages dus à chaque membre** — une affiche chaque trimestre, un nom au générique — relèvent de
  `phil/benefit-deliverables`.

### La couture avec eux

Le module donne à ces paquets ce à quoi un abonné a droit, et rien qu'ils auraient à défaire :

| Ce qu'ils lisent | Par | Contient |
|---|---|---|
| Les fonctionnalités qu'une personne peut utiliser maintenant | `FeatureAccess::features()` et `grants()` | Celles de ses propres abonnements, de ses organisations — chaque membre, ou les détenteurs de places —, des cadeaux échangés, et les étapes de relance d'un renouvellement en souffrance |
| Qui détient un abonnement, de quel palier, jusqu'à quand | `SubscriptionRepository::forSubscribers()`, `Subscription::offerCode()`, `periodEnd()`, `state()`, `payer()` | L'identifiant stable de l'abonné, jamais une adresse courriel ; qui paie un abonnement offert |
| Qui, dans une organisation, l'utilise | `SeatRepository::holdsSeat()`, et l'`OrganizationMembership` de l'application | Les membres qui détiennent les premières places d'une offre qui les compte |
| Où va un envoi | `DeliveryAddresses::of()` et `ofOrder()` | L'adresse d'un abonnement, et la copie figée dans chaque commande, jusqu'à leur effacement |

- **Un droit sur le contenu est un nom de fonctionnalité** : `phil/content-access` associe une page, un fichier ou un
  épisode à une fonctionnalité (`episodes:early`), et interroge `FeatureAccess::grants()` ; ce module ne sait jamais ce
  qu'ouvre une fonctionnalité.
- **Un avantage est dû par période payée** : `phil/benefit-deliverables` lit l'état et la fin de période d'un abonnement
  pour savoir qui se voit dû quoi ce trimestre, et garde ce qu'il a livré dans son propre stockage ; ce module ne garde
  aucune livraison. Il lit l'adresse où un avantage est expédié par un port qui lui est propre, qu'un adaptateur
  alimente à partir de `DeliveryAddresses`.
- **Aucun des deux paquets n'est requis** : ce module ne dépend d'aucun des deux, et ni l'un ni l'autre n'a besoin de
  plus que les lectures ci-dessus.
- **Les organisations elles-mêmes**, leurs membres et leurs rôles, relèvent de l'application : le module les lit par
  `OrganizationMembership`, et ne dépend d'aucun paquet de locataires.

## Les ports du noyau qu'il implémente

- `SellableResolver`, pour les types de référence `offer`, `subscription` et `gift` : `OfferSellables`, étiqueté par
  l'autoconfiguration du noyau.
- `OrderProcessor`, pour le calcul au prorata : `ProrationOrderProcessor`, étiqueté après les options et les
  promotions, avant les taxes. Ses ajustements sont du type `Proration` du noyau, que le noyau taxe avec la ligne.

Ce que décide le noyau reste décidé ici : les prix viennent des résolveurs, jamais d'un formulaire ; chaque montant est
un `Money` dans la devise de la commande ; les taxes sont calculées par le noyau sur chaque ligne.
