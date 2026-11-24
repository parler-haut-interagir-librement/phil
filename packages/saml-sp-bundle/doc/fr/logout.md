<!-- phil-dev source-sha256=8f97e5037fb6679f36ab8344e2983a8b646dfd50353a62818effdace49a8295b -->
# Déconnexion unique

## Depuis l'application

La déconnexion de Symfony, `/logout` dans le pare-feu, met fin à la session de l'application. Quand le jeton vient d'un
fournisseur d'identité dont `single_logout` est actif, dont les métadonnées ont un service de déconnexion, et que le
fournisseur de service a sa clé de signature, la réponse de la déconnexion devient la `LogoutRequest` signée adressée au
fournisseur d'identité : la personne y est aussi déconnectée. Le fournisseur d'identité répond sur `/saml/{idp}/slo` ; la
réponse est contrôlée par rapport à la requête, et la personne va à `logout_target_path`. Une réponse refusée est
annoncée par `SamlResponseRefused` ; la session de l'application avait déjà pris fin.

Quand le fournisseur d'identité n'a pas de service de déconnexion, la déconnexion reste locale : la personne reste
connectée chez le fournisseur d'identité, et une nouvelle connexion peut ne pas lui demander son mot de passe.

## Depuis le fournisseur d'identité

Quand une personne se déconnecte du fournisseur d'identité, ou d'une autre application de l'organisation, le
fournisseur d'identité envoie une `LogoutRequest` à `/saml/{idp}/slo`. Le bundle vérifie sa signature, met fin à la
session du navigateur qui la porte quand cette session est celle de la personne, annonce `SamlLogoutReceived`, et répond
par une `LogoutResponse` signée.

Une requête de déconnexion portée par le navigateur n'atteint que la session de ce navigateur, et seulement quand le
navigateur envoie son témoin de session : un témoin de session `SameSite=Lax`, le choix par défaut de Symfony, suit la
redirection de HTTP-Redirect mais non le `POST` intersite de HTTP-POST ; donnez donc au fournisseur d'identité le
service de déconnexion sur HTTP-Redirect. Pour mettre fin aux autres sessions de la personne — un autre appareil —,
écoutez `SamlLogoutReceived` : il porte l'identifiant de nom et les index de session, que le jeton de chaque session
garde dans ses attributs `phil_saml_sp.name_id` et `phil_saml_sp.session_index`.

## Liaisons

Les messages de déconnexion sont envoyés sur HTTP-Redirect quand le fournisseur d'identité l'offre, sur HTTP-POST
autrement, toujours signés. Les messages reçus sont acceptés sur les deux, signés dans la requête ou à l'intérieur ; un
message non signé est refusé avec 400.
