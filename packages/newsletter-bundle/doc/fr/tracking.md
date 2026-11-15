<!-- phil-dev source-sha256=93db1e634bf69350e5a700d1834817606278c4bcdc6eb7e5324b26f226f55409 -->
# Suivi

## Les clics

Quand une campagne compte les clics, chaque lien `http` ou `https` de son corps est remplacé par un lien signé du
bundle, qui redirige vers l'adresse que la campagne garde pour lui — jamais vers une adresse que porte le lien, de sorte
que le bundle n'est pas une redirection ouverte. Les liens de la mise en page, vers la désinscription et vers le centre
de préférences, ne sont jamais suivis.

| `ClickTracking` | Compte |
|---|---|
| `aggregate` | Un total par lien de la campagne. La valeur par défaut : le lien ne nomme aucun destinataire. |
| `per_recipient` | Le total, et les clics de chaque destinataire ; distribue `LinkClicked`, que lit le déclencheur `newsletter.link_clicked` des automatisations. Un choix explicite de la campagne. |
| `off` | Rien : les liens sont laissés tels quels. |

```php
$engagement->clicks($campaignId);              // [link index => clicks]
$engagement->recipientClicks($subscriberId);   // per recipient, when chosen
```

## Les machines

Les analyseurs des fournisseurs de courriel et les aperçus de liens ouvrent les liens avant les personnes. Ils sont
écartés des comptes sans lire d'adresse IP ni d'agent utilisateur :

- une requête `HEAD` ou `OPTIONS` ;
- une requête dont l'en-tête `Sec-Purpose`, `Purpose`, `X-Purpose` ou `X-Moz` dit qu'elle est une prélecture ou un
  aperçu ;
- un clic à moins de `clicks.minimum_seconds`, dix par défaut, de l'envoi du message.

La redirection a lieu quand même, pour qu'un analyseur voie un lien qui fonctionne.

## Ce qui n'est jamais suivi

Aucun message ne porte de pixel espion, et rien n'enregistre l'ouverture d'un message : l'ouverture n'est pas un signal
fiable, et elle regarde le lecteur. Aucun déclencheur des automatisations n'y réagit.
