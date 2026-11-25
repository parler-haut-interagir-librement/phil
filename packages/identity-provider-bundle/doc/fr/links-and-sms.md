<!-- phil-dev source-sha256=da97b63c1046c0e9b076afa774871a2aea2f2d4bd07aaf53f946dbce79dc7965 -->
# Liens de connexion et numéros de téléphone

## Liens de connexion

Le module `login_link` offre `/login/link` : l'adresse saisie, un lien de l'authentificateur `login_link` de Symfony
Security qui lui est envoyé par les transports du composant Mailer, et la même réponse que l'adresse ait un compte ou non. Le
pare-feu configure l'authentificateur, sa route de vérification `phil_identity_provider_login_link_check`, sa durée de
vie, ses utilisations et les propriétés sur lesquelles il signe — `userIdentifier` et `passwordChangedAt` empêchent
un lien de survivre à un changement de mot de passe :

```yaml
login_link:
    check_route: phil_identity_provider_login_link_check
    signature_properties: [userIdentifier, passwordChangedAt]
    lifetime: 600
    max_uses: 1
    check_post_only: true
```

Le lien n'est consommé que par POST. Le suivre affiche une page avec un seul bouton, « Me connecter », dont le
formulaire envoie les paramètres du lien à la même route, sans script ; l'authentificateur les vérifie là et connecte la
personne. Un analyseur de courriel ou un aperçu de lien qui suit le lien ne fait que lire cette page, et l'usage unique
reste à la personne. Le bundle active `check_post_only: true` sur chaque authentificateur `login_link` qui vérifie
`phil_identity_provider_login_link_check`, quoi qu'en dise le pare-feu (`LoginLinkPostOnlyPass`). La page ne reporte que
les paramètres du lien — des noms de lettres, de chiffres, de `_`, de `.` et de `-`, de 16 caractères au plus —, n'envoie
aucun référent (`Referrer-Policy: no-referrer`) et reste hors des caches et des moteurs de recherche.

Le lien est généré sur `public_origin`, jamais sur l'hôte que la requête a nommé : voir
[Où mènent les liens](accounts.md#où-mènent-les-liens).

Un lien de connexion n'indique aucune méthode : il compte pour `IS_AUTHENTICATED_RECENTLY`, non pour `PHIL_STEP_UP`
d'un compte qui a une méthode plus forte, et un compte qui a une application d'authentification se voit demander son
code ensuite.

## Numéros de téléphone

Le module `sms` vérifie un numéro de téléphone : `/account/phone` prend un numéro au format E.164 — les espaces, les
tirets, les points et les parenthèses sont retirés —, envoie six chiffres par le port `SmsSender`, et vérifie le
numéro quand le code revient. Le code est conservé haché dans la session avec le numéro, une expiration de
`sms.lifetime` secondes et `sms.attempts` essais. Le `SmsSender` par défaut est un texteur du composant Notifier
sur ses transports SMS, sans bus, vers le transport SMS que l'application a configuré ; `sms.sender` nomme plutôt un
service à elle, qui garde aussi le code hors de toute file. Le débit d'envoi relève du limiteur de débit de l'application placé devant la route.

Un numéro vérifié permet à l'application de joindre la personne, et figure dans les revendications de la portée
`phone`. Les messages texte comme deuxième facteur sont prévus, non implémentés : un SMS peut être intercepté, et
l'application d'authentification ou une clé d'accès est le deuxième facteur que le bundle offre.

## Pourquoi les courriels de compte et les SMS ne passent jamais par Messenger

Un lien de vérification, un lien de réinitialisation et un lien de connexion donnent chacun le compte à quiconque les
détient, aussi longtemps qu'ils durent — jusqu'à une heure pour un lien de réinitialisation — ; un code envoyé par
message texte prouve le numéro. Le bundle les remet à un transport dans la requête qui les demande, et aucun message de
Messenger ne les porte jamais :

- `MailerAccountMailer` rend chaque courriel — sa partie HTML et sa partie texte — avec le moteur de rendu du corps de
  Twig, puis le remet aux transports du composant Mailer (`mailer.transports`). Il n'appelle jamais
  `MailerInterface`, qui met un `SendEmailMessage` sur le bus quand Messenger l'achemine : le courriel attendrait non
  rendu, son contexte et le lien qu'il contient, dans le transport, dans le transport des échecs après un essai
  raté, et dans leurs sauvegardes. En lire un seul suffirait pour prendre les comptes.
- Le `SmsSender` par défaut est un texteur sur les transports SMS du composant Notifier (`texter.transports`), sans
  bus. Le texteur `texter` met un `SmsMessage` sur le bus quand Messenger l'achemine, avec le numéro et le code.

La recette de Messenger achemine les deux vers le transport `async` :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Symfony\Component\Mailer\Messenger\SendEmailMessage: async
            Symfony\Component\Notifier\Message\SmsMessage: async
```

Cet acheminement reste juste pour les autres courriels de l'application, et ne change rien pour ceux du bundle. Un
service de l'application qui remplace `AccountMailer` ou `SmsSender` garde la même règle : il envoie sur-le-champ, ou
il met sur le bus un message qui ne porte que des identifiants — le compte et le genre de lien —, et son gestionnaire
construit le lien dans le *worker*.

Chaque courriel écrit son lien en entier dans sa partie texte, `@PhilIdentityProvider/email/<message>.txt.twig`, pour
qu'un lecteur qui n'affiche pas le HTML voie quand même le lien ; et porte un `TrackingHeader` qui désactive le suivi
des ouvertures et des clics que le fournisseur du transport effectue, pour que le fournisseur ne réécrive pas le lien
afin de compter ses clics.
