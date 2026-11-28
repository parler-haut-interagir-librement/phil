<!-- phil-dev source-sha256=fcecaa0004c910cf9536e6a99637c93102d57e8a05bb6be3ffb51e1af75fea5a -->
# Sécurité

## Ce dont le paquet protège

- **Le pistage et le contenu distant dans un reçu.** Un site peut modifier ses gabarits ; `TransactionalCheck` refuse
  tout reçu produit qui charge quoi que ce soit — une image, une feuille de style, un cadre, un script, un média — ou
  qui contient un lien avec un paramètre de pistage, avant que le courriel soit construit. Les images sont intégrées et
  affichées en `cid:` seulement.
- **Les renseignements personnels sur un transport de messages.** Les messages de Messenger portent seulement le numéro
  d'un reçu, vérifié comme ASCII visible ; le *worker* relit le reçu et son destinataire, et les *handlers* envoient par
  un transport de Mailer plutôt que de mettre le courriel sur un transport de Messenger.
- **Les montants qui diffèrent du reçu.** Chaque montant est copié du reçu tel que son émetteur l'a émis, dans sa
  devise ; une valeur d'une autre devise est refusée, et le paquet ne calcule aucun total.
- **Un montant admissible de zéro ou moins sur un reçu officiel de don.** `Donation::of()` refuse un avantage aussi
  grand que le don, et un avantage sans sa description.
- **L'injection d'en-têtes et de noms de fichier.** Un numéro est de l'ASCII visible ; le nom de fichier du PDF garde
  seulement les lettres, les chiffres, `.`, `_` et `-` ; les textes qu'un reçu imprime ne contiennent ni saut de ligne
  ni caractère de contrôle.
- **Les entrées sans borne.** Au plus 500 lignes, frais et taxes sur un reçu, 20 taxes et options sur une ligne, 10
  lignes d'adresse, 500 caractères par texte, 2 Mio de HTML vérifiés.

## Ce qui revient à l'application

- Qui reçoit un reçu : `RecipientDirectory` donne l'adresse, et l'application vérifie qu'elle appartient à l'acheteur ou
  au donateur.
- La numérotation des reçus de don, séquentielle et sans trous, dans la transaction qui les garde, et leur conservation
  pendant les années que la loi exige.
- Savoir si un don donne droit à un reçu officiel.
- L'envoi par un transport qui authentifie le domaine de l'expéditeur.
