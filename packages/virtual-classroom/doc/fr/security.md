<!-- phil-dev source-sha256=b3481b073506d5c935559ff0d18388dc5ed9c31fdcb98ec57531b8711e27c87b -->
# Sécurité

## Secrets

- Le secret de BigBlueButton ne voyage jamais : chaque appel porte une somme de contrôle de SHA-2, et le jeton
  d'enregistrement et le jeton porteur de l'assiduité sont vérifiés contre lui en temps constant, contre les
  algorithmes que l'adaptateur accepte, jamais celui que demande un jeton seul et jamais `none`.
- Les secrets et la clé privée sont marqués `#[SensitiveParameter]` : ils n'apparaissent pas dans une trace de pile.
- La signature du webhook de Jitsi est comparée en temps constant, son instant à moins de cinq minutes, de sorte
  qu'une notification rejouée plus tard soit refusée.
- Chaque secret fait 16 octets ou plus ; une clé JaaS est une clé RSA.

## Réponses et notifications

- Une réponse XML avec un type de document est refusée, pour qu'aucune entité ne soit déclarée ni développée et que
  rien ne soit chargé depuis le réseau.
- Chaque réponse, notification et jeton est borné en taille et en profondeur ; voyez [Erreurs et budgets](errors.md).
- Toute adresse lue depuis un serveur, l'adresse d'un enregistrement par exemple, est lue par `phil/url` ; une adresse
  qui n'est pas une adresse `http(s)` d'un hôte public est écartée.

## Renseignements personnels

- Un participant voyage par l'identifiant stable de son compte ; une adresse courriel est refusée comme identifiant.
- Le nom d'affichage va dans le lien d'accès seulement, jusqu'au serveur ; aucune exception ni aucun journal de la
  bibliothèque ne le contient.
- Les enregistrements et l'assiduité sont des renseignements personnels des participants : l'application les conserve
  selon ses règles de conservation et les remet dans une demande d'accès.

## Ce qui revient à l'application

- La preuve des adresses que les serveurs rappellent, et la vérification que la salle d'une notification est l'une de
  ses salles sur ce serveur.
- Qui est modérateur.
- Où vont les liens d'accès : un lien laisse entrer son détenteur, pour son rôle, jusqu'à la fin de la salle ou
  l'expiration du jeton. Envoyez-le à son participant seulement, en https, par une redirection qui n'est pas mise en
  cache.
