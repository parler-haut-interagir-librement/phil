<!-- phil-dev source-sha256=19c3bab23877428eb497dad0a31ea056afdd8932a1fa1fdfcafd05feff30b16e -->
# Déconnexion unique

La déconnexion unique est facultative : un fournisseur d'identité sans service de déconnexion dans ses métadonnées n'en
a pas, et `MessageFactory` lance `InvalidMetadata` quand on lui demande un message de déconnexion pour lui.

## Lancée par le fournisseur de service

1. L'application termine sa propre session, puis écrit une `LogoutRequest` avec
   `MessageFactory::logoutRequest($sp, $idp, $nameId, $nameIdFormat, $sessionIndex)` — l'identifiant de nom et l'index
   de session de la `SamlIdentity` avec laquelle la personne s'est connectée — et en garde l'ID.
2. `MessageSender::send()` la place sur le service de déconnexion du fournisseur d'identité, de préférence sur
   HTTP-Redirect, toujours signée.
3. Le fournisseur d'identité répond par une `LogoutResponse` au service de déconnexion du fournisseur de service :
   `HttpRedirect::receive($query, $certificates)` ou `HttpPost::receive($field)` la décode, et
   `LogoutProcessor::response($message, $idp, $sp, $requestId)` l'accepte, ou lance `IdentityProviderRefused` pour un
   statut autre que le succès — `PartialLogout`, par exemple.

## Lancée par le fournisseur d'identité

1. Le fournisseur d'identité envoie une `LogoutRequest` au service de déconnexion du fournisseur de service.
2. `LogoutProcessor::request($message, $idp, $sp)` la vérifie et retourne une `LogoutRequest` : son ID, l'identifiant de
   nom de la personne et les index de session qu'elle nomme.
3. L'application termine les sessions de cette personne, puis répond avec
   `MessageFactory::logoutResponse($sp, $idp, $request->id)`, avec succès sauf si l'on passe `false`.

## Ce qui est contrôlé

Un message de déconnexion est signé : sur HTTP-Redirect, par la signature de la requête, que `HttpRedirect::receive()`
vérifie avec les certificats du fournisseur d'identité — le texte signé est la requête telle que reçue, jamais encodée
de nouveau —, et sur HTTP-POST, par la signature à l'intérieur, que `LogoutProcessor` vérifie comme celle d'une réponse.
Un message de déconnexion non signé est refusé, de sorte qu'aucune page ne puisse déconnecter une personne en envoyant
son navigateur au service de déconnexion. Son émetteur est le fournisseur d'identité, sa destination, quand elle est
présente, le service de déconnexion du fournisseur de service, son instant d'émission se situe dans le délai accepté, et
son `NotOnOrAfter`, pour une requête, est dans le futur ; la réponse répond à la requête que ce navigateur a envoyée.
