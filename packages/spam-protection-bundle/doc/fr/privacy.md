<!-- phil-dev source-sha256=237e7ac56c9afafd39ae8a1a36bd91da57d98a047a699c98fc9d8aa2117c7c9a -->
# Vie privée

| Donnée | Gardée | Pendant combien de temps |
|---|---|---|
| Le nonce du jeton | Dans le stockage des jetons utilisés : le pool de cache, ou la table de `used_tokens.connection` | `timing.maximum` secondes ; dans la table, jusqu'à la prochaine purge |
| Une empreinte à clé du réseau du client, par formulaire : l'adresse IPv4, ou le `/64` d'une adresse IPv6 | Dans le stockage du limiteur de débit | La fenêtre du limiteur |
| La portée du formulaire et les signaux d'un envoi retenu | Dans le journal, canal `spam_protection` | La durée de rétention du journal |

**Jamais recueillis** : l'adresse elle-même, les témoins, les empreintes de navigateur, le comportement de la souris
ou du clavier, les scores de réputation de tiers. **Jamais envoyé nulle part** : le contrôleur Stimulus calcule une
empreinte localement et écrit un nombre dans le formulaire.

L'empreinte du client est un HMAC-SHA256 sous une clé dérivée du secret de l'application par HKDF-SHA256 sous
`phil_spam_protection.client_key`, qu'aucun autre usage du secret ne partage : on ne peut pas la ramener à une adresse
sans le secret, et elle change avec lui. Une adresse IPv6 est hachée sous la forme de son `/64`, le bloc qu'un hôte
reçoit et dans lequel il peut faire tourner ses adresses librement, pour qu'en changer n'échappe pas à la limite de
débit ; une adresse IPv4 écrite en IPv6 (`::ffff:203.0.113.7`) est hachée comme l'adresse IPv4. Le jeton contient la
portée, une date et un nonce aléatoire — rien sur la personne.
