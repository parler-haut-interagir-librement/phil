<!-- phil-dev source-sha256=8115dec77920b5c4c05fd454d84140ad756f444cc9e75fe9ae1b3f94c56a820b -->
# Connexion

## La requête

`MessageFactory::authnRequest($sp, $idp, forceAuthn: false, isPassive: false)` écrit une `AuthnRequest` :

- un ID de 160 bits aléatoires, que la réponse répète dans `InResponseTo` ;
- le service de connexion du fournisseur d'identité sur sa `requestBinding` (HTTP-Redirect par défaut), ou sur l'autre
  liaison quand ses métadonnées n'ont que celle-là ;
- le service de consommation d'assertions du fournisseur de service, sur HTTP-POST ;
- une `NameIDPolicy` avec le format du fournisseur de service quand il en nomme un, et `AllowCreate` ;
- `ForceAuthn` pour demander à la personne de s'authentifier de nouveau, `IsPassive` pour demander au fournisseur
  d'identité de ne rien afficher.

La requête est signée quand le fournisseur de service signe ses requêtes (la valeur par défaut) ou quand les métadonnées
du fournisseur d'identité posent `WantAuthnRequestsSigned`. `MessageSender::send($message, $sp->signing, $relayState)`
donne un `Outbound` : sur HTTP-Redirect, l'adresse où rediriger, la requête compressée dans la requête HTTP et signée là
avec RSA-SHA256 ; sur HTTP-POST, l'action et les champs d'un formulaire, la requête signée à l'intérieur.

L'application garde l'ID de la requête jusqu'au retour de la réponse, avec `PendingRequests` : `CachePendingRequests` le
garde dix minutes dans une réserve PSR-6, et `take()` l'oublie en le retournant, de sorte qu'une réponse ne réponde à une
requête qu'une fois. Une `PendingRequest` contient aussi l'endroit où la personne va une fois connectée et, quand
l'application lie la requête au navigateur qui l'a envoyée, l'empreinte d'un jeton que ce navigateur détient.

## La réponse

`ResponseProcessor::process($samlResponse, $idp, $sp, $requestId)` lit le champ `SAMLResponse` tel qu'il est posté :

1. le champ est en base64 dans le budget d'un message, 256 Kio par défaut ; le document est lu par `SafeXml` ;
2. la racine est une `Response` SAML 2.0, chaque `ID` du document est unique, et le document contient exactement une
   assertion — en clair ou chiffrée —, enfant direct de la réponse ;
3. le statut est un succès ; tout autre statut lance `IdentityProviderRefused`, qui porte le code de statut et son
   second niveau, `AuthnFailed` ou `RequestDenied` par exemple ;
4. l'émetteur de la réponse, quand il est présent, est le fournisseur d'identité ; sa destination est le service de
   consommation d'assertions, et une réponse signée doit en avoir une ; son `InResponseTo` est la requête de ce
   navigateur ; elle a été émise dans les dix dernières minutes, à un décalage d'horloge près ;
5. une signature sur la réponse doit se vérifier ; l'assertion chiffrée est déchiffrée ; une signature sur l'assertion
   doit se vérifier, et l'assertion doit être signée, sauf si le fournisseur d'identité est configuré avec
   `wantAssertionsSigned: false` et que la réponse est signée ;
6. l'assertion est SAML 2.0, émise par le fournisseur d'identité, dans le délai accepté ; son sujet a un identifiant de
   nom et une confirmation par porteur dont le destinataire est le service de consommation d'assertions, dont
   l'`InResponseTo` est la requête, et dont le `NotOnOrAfter` est dans le futur ; ses conditions sont valides
   maintenant et la limitent à une audience qui nomme le fournisseur de service — chaque `AudienceRestriction` doit le
   faire ; sa déclaration d'authentification a un instant, et un `SessionNotOnOrAfter` dans le futur quand elle en a un ;
7. l'assertion n'a pas été acceptée auparavant : `ReplayGuard::firstUse()` retient son ID jusqu'à la dernière de ses
   expirations plus le décalage d'horloge.

Ce qui est lu — l'identifiant de nom, la session, les attributs — l'est dans les éléments vérifiés, par les enfants
directs seulement.

## L'identité

`SamlIdentity` contient l'émetteur, l'identifiant de nom et son format, le `SessionIndex`, l'instant d'authentification,
la fin de la session chez le fournisseur d'identité, la classe de contexte d'authentification, l'ID de l'assertion,
chaque attribut par nom et les champs de l'utilisateur associés par la table des attributs. `subject()` est
l'identifiant associé — l'identifiant d'objet de Microsoft Entra ID, par exemple — ou l'identifiant de nom : la valeur
sur laquelle indexer un compte, puisqu'une adresse courriel peut changer. Voir [Attributs](attributes.md).

## Réponses non sollicitées

Une réponse qui ne répond à aucune requête, envoyée par le fournisseur d'identité quand la personne ouvre l'application
depuis son portail, est refusée sauf si le fournisseur d'identité est configuré avec `allowUnsolicited: true` ; passez
`null` comme ID de requête. Une telle réponse ne doit pas porter d'`InResponseTo`, et une assertion qui répond à une
requête doit répondre à celle de ce navigateur : une réponse capturée depuis un autre navigateur est refusée dans les
deux cas. Les réponses non sollicitées renoncent à lier une connexion au navigateur qui l'a demandée : ne les permettez
que pour un fournisseur d'identité dont le portail l'exige.

## Assertions chiffrées

Une `EncryptedAssertion` est déchiffrée avec les paires de clés de `ServiceProvider::$encryption`, la courante d'abord
et la précédente pendant un renouvellement. Le transport de clé est RSA-OAEP, les données sont en AES, GCM ou CBC ; voir
[Sécurité](security.md#chiffrement). Un fournisseur d'identité configuré avec `wantAssertionsEncrypted: true` doit
chiffrer ses assertions ; sans paire de clés de chiffrement, une assertion chiffrée est refusée.

## Temps

L'horloge est l'horloge PSR-20 donnée au processeur. Le décalage d'horloge de `SecurityPolicy`, deux minutes par défaut
et dix au plus, élargit chaque contrôle de temps dans les deux sens ; `maxAge`, dix minutes par défaut, borne l'âge
qu'une réponse peut avoir.
