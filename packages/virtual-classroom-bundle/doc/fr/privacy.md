<!-- phil-dev source-sha256=e10be546eb87d46ae4486038b27ccf643ef9143d90bcba82715ea459e8b8f0da -->
# Vie privée

Le bundle conserve, d'une personne, les **séjours** de son identifiant de participant : quelle salle, quand elle est
arrivée et repartie. Les salles — un titre, un propriétaire, des instants — et les métadonnées des enregistrements ne
nomment personne. Les enregistrements eux-mêmes, conservés par les serveurs, contiennent les voix et les visages des
participants : leur conservation relève des serveurs.

Avec phil/privacy-bundle activé et `privacy.enabled` à vrai, `ClassroomPrivacy` est le module `virtual_classroom` des
demandes d'accès et d'effacement. La personne est le participant, nommé par l'identifiant stable de son compte.

- L'exportation donne chaque séjour : `room`, `joined_at`, `left_at`, en dates ATOM.
- L'effacement supprime les séjours, et rapporte la table `<prefix>_stay` comme supprimée. Il s'exécute de nouveau sans
  dommage.

Le nom d'affichage d'un participant n'est jamais conservé par le bundle : il va au serveur dans le lien d'accès
seulement. Les journaux du bundle et de phil/virtual-classroom contiennent des identifiants de salle, jamais de
participants.
