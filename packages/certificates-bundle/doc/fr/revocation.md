<!-- phil-dev source-sha256=50c6c2dfd23a0ee88002a79ff9e7da19b4e4e82f261921d432cb76056b150093 -->
# Révocation

```php
$revocation->revoke('7KQ2-M9XA-04TZ', 'The assessment was taken by someone else.');
```

`Phil\CertificatesBundle\Application\Revocation` révoque un certificat par son code, tel qu'imprimé ou tel que tapé,
avec un motif de 1 à 500 caractères. Un certificat est révoqué une seule fois : le révoquer de nouveau, ou donner un
code inconnu, lève `CertificateRefused`. La révocation est définitive; une personne apprenante qui obtient de nouveau
le certificat en reçoit un nouveau.

Une fois révoqué, la page du certificat l'indique avec le motif, son PDF et son attestation répondent `410 Gone`, et
son bit est activé dans la liste d'état que publie l'émetteur. Un certificat expiré n'est pas révoqué : son bit reste
inactif, et sa page et ses fichiers sont toujours servis ([Expiration et recertification](recertification.md)). Un
certificat révoqué n'est pas renouvelé.

## Les listes d'état

Chaque certificat prend le prochain indice d'état au moment de son attribution. `StatusListLayout` scinde les indices
en listes de `status_lists.size` : l'indice `i` est le bit `i mod size` de la liste `<prefix>-<i div size>`, et le
registre ne manque donc jamais d'indices. Le `credentialStatus` de l'attestation pointe vers cette liste, servie par
`phil/open-badges-bundle` à `/status/<list>` sous son préfixe.

`CertificateStatusLists` implémente le port `StatusLists` de `phil/open-badges-bundle` : pour une liste de la
disposition, il lit les indices des certificats révoqués dans son intervalle avec
`CertificateRepository::revokedIndexes()`. Une liste d'un autre nom est demandée au service `status_lists.others`,
quand l'application a ses propres listes; sinon, elle est inconnue.

La taille est celle de `phil_open_badges.status_lists.size`, ou moins : une liste contient autant de bits. La valeur
par défaut, 131 072, est la plus petite que permet la spécification, ce qui cache quel certificat un vérificateur
vérifie parmi beaucoup d'autres.
