<!-- phil-dev source-sha256=8941fc0dd931b0835ad717e9e433e051f06d10d84509abddf4c636bbd647d2de -->
# Anonymat

## Ce que garde un sondage anonyme

| Table | Ligne | Ce qu'elle contient |
|---|---|---|
| `phil_poll_vote` | le bulletin | Un identifiant aléatoire, le sondage, les choix, et l'empreinte SHA-256 du reçu. Aucun votant, aucune heure. |
| `phil_poll_participation` | la participation du votant | Le sondage et le votant. Aucune heure, aucun bulletin. |

La participation empêche un deuxième bulletin du même votant. Rien dans l'une ou l'autre table ne dit quel bulletin
appartient à qui : les identifiants des bulletins sont aléatoires (UUID version 4, pas une version ordonnée dans le
temps), aucune ligne ne porte d'heure, et la clé de la participation est le sondage et le votant, sans référence au
bulletin.

## Le reçu

Pour un sondage anonyme, `cast()` renvoie un reçu : 32 octets aléatoires en base64 sûr pour les URL, soit
43 caractères. Le bundle n'en garde que l'empreinte. L'application montre le reçu au votant une seule fois — sur la
page de confirmation, dans le courriel de confirmation — et ne le conserve jamais à côté du votant.

Avec le reçu, le votant modifie son bulletin pendant que le sondage est ouvert (`changeWithReceipt()`) et le relit
(`ballotOfReceipt()`). Quiconque détient un reçu peut modifier son bulletin, pourvu que la politique le laisse voter :
un reçu est un secret, comme un lien de réinitialisation de mot de passe. Un bulletin anonyme n'est jamais retiré : le
retirer obligerait à supprimer la participation, ce qui révélerait de quel bulletin il s'agissait.

## Ce que l'anonymat ne couvre pas

L'anonymat protège les bulletins de quiconque lit les tables, un export ou une sauvegarde. Il ne les protège pas :

- des journaux de la base de données ou du serveur Web, qui peuvent noter la requête qui a déposé un bulletin à côté de
  son heure et de son utilisateur ;
- de l'ordre physique des lignes sur certains moteurs — un bulletin et sa participation sont écrits dans la même
  transaction, et un moteur qui ajoute les lignes à la suite les range près l'une de l'autre ;
- d'un écouteur de l'événement `VoteCast` qui note l'utilisateur de la requête : l'événement ne nomme que le sondage,
  mais il est diffusé pendant cette requête ;
- d'un sondage qui a très peu de votants, ou d'un votant seul à choisir une option.

Une application qui a besoin d'un vote secret au sens le plus fort — une élection soumise à des règles de droit —
garde ses journaux de base de données et de serveur Web exempts de ces détails, et décide à partir de quel petit nombre
de votants il y en a trop peu pour afficher les résultats.
