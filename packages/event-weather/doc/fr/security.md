<!-- phil-dev source-sha256=777ad1017541071ee4fb0e55e17cbe7b5331884f78c94029b930a395f729945d -->
# Sécurité

## Les requêtes

Les adaptateurs interrogent des points de terminaison `https` fixes de leurs fournisseurs, ou celui que configure
l'application, un `Phil\Url\Url` ; rien de ce que tape un visiteur n'atteint une adresse. La requête ne porte que les
coordonnées de l'événement — coupées à quatre décimales pour MET Norway, sous forme d'un carré autour d'elles pour
Environnement Canada — et jamais l'événement, son organisateur ni ses visiteurs. Le `User-Agent` est le `Contact` de
l'application, vérifié comme tenant sur une ligne : un nom de lettres, de chiffres et de `._-/+`, et une adresse Web ou
de courriel.

Chaque requête est bornée : 10 secondes de silence, 20 secondes au total, trois redirections, 8 Mio de réponse lus par
morceaux. Les fournisseurs sont des services de confiance, non des adresses fournies par des membres : la bibliothèque
ne fait pas passer leurs réponses par un garde d'adresses, et l'application lui donne un client HTTP sans mandataire
dont elle se méfie.

## Les réponses

Le JSON d'un fournisseur est décodé avec une imbrication bornée à 64 niveaux, et lu sans se fier à sa forme : une clé
manquante ou une valeur d'un autre type est nulle, un instant sans son décalage n'est pas lu, un pourcentage ou une
direction hors limites est ramené dans les limites ou écarté. Un fournisseur qui envoie ce que son format ne décrit
pas lève `Exception\WeatherUnavailable`, qu'`EventWeather` journalise et transforme en prévision indisponible.

Le lecteur CAP refuse un type de document — la voie des attaques par expansion d'entités —, lit sans le réseau, refuse
ce qui n'est pas en UTF-8 ou contient un caractère de contrôle, et borne les octets et les blocs `<info>`. Il est
soumis au fuzzing — voir [Tests](testing.md).

## Les textes

Les résumés des périodes et les textes des alertes sont les mots des fournisseurs, gardés tels qu'ils sont venus : du
texte brut que l'application échappe quand elle en produit le rendu, comme le fait Twig par défaut. Le `web` d'une
`WeatherAlert` est une adresse `http` ou `https` lue par `phil/url`, ou nul.

## Ce qui reste à l'application

- Le cache qui garde les réponses entre les requêtes, et son stockage.
- Le rythme des requêtes : une page qui affiche plusieurs événements en un même lieu partage une réponse en cache ;
  une application qui prévoit des milliers de lieux par minute parle d'abord aux fournisseurs.
- L'échappement des textes des fournisseurs dans ses pages, et l'affichage des mentions de source.
