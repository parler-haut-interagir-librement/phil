<!-- phil-dev source-sha256=a69106087edfcdddf9efbe93097dcf06ff913e9e4f9cc0bfd1d7325a1a62e6e5 -->
# La garde

## Adresses

`AddressGuard::address($text)` lit une adresse comme phil/url la lit, et refuse avec `RefusedAddress` :

- toute adresse qui n'est pas une adresse `http` ou `https` absolue ;
- un nom d'utilisateur ou un mot de passe, même vides ;
- un hôte qui est une adresse IP, sous n'importe laquelle de ses écritures — `127.0.0.1`, `127.1`, `2130706433`,
  `0x7f000001`, `[::1]` — ou une étiquette seule, comme `localhost` ;
- le port 0, et une adresse de plus de 2 048 caractères une fois normalisée.

Une adresse que la garde accepte est normalisée : schéma et hôte en minuscules, hôte international sous sa forme ASCII,
port par défaut retiré.

## Redirections

`AddressGuard::redirect($from, $location)` résout un `Location` à partir de l'adresse qui a répondu, comme un
navigateur le résout — absolu, relatif à la racine ou au chemin, sans schéma, avec `..` — puis vérifie le résultat
comme une première adresse. `javascript:`, `data:`, `file:` et une redirection vers une adresse IP sont refusés. Une
redirection de HTTPS vers HTTP est suivie : la garde porte sur la destination de la requête, non sur la façon dont elle
voyage.

## Réseaux privés

Un nom d'hôte est résolu par `NoPrivateNetworkHttpClient`, qui refuse une adresse de la boucle locale, d'un réseau
privé, de la plage de lien local — les points de terminaison de métadonnées des nuages — ou d'une plage réservée, en
IPv4 comme en IPv6, et se connecte à l'adresse qu'il a vérifiée, de sorte qu'un nom ne puisse pas se résoudre ailleurs
entre la vérification et la connexion. Il vérifie de nouveau l'adresse à laquelle le transport s'est connecté. Un refus
est une `RefusedAddress`. Aucun mandataire n'est employé, pas même celui que nomment `http_proxy` ou `https_proxy` dans
l'environnement : un mandataire résoudrait l'hôte de nouveau, au-delà de l'adresse vérifiée.

## Ce que la garde ne couvre pas

| Sujet | Où il se traite |
|---|---|
| Quels hôtes un site lit, tout court | L'application : une liste d'hôtes permis avant le client |
| À quelle fréquence un membre fait lire le site | Un limiteur de débit avant le client, par membre ou par adresse |
| Ce que contient le corps | Le lecteur du corps : un analyseur HTML borné en octets, un analyseur XML sans entités externes |
