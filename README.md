# Déploiement afup

## Travail avec un environnement local
Nécessite vagrant.

Pour tester le déploiement du repo web (par exemple):

1. Démarrer la vagrant: `vagrant up`
2. S'y connecter: `vagrant ssh`
3. Changer pour l'utilisateur "afup": `sudo su afup`
4. Créer les fichiers de configuration dans /home/afup/afup.org/shared:
  - app/config/parameters.yml
  - configs/application/config.php
5. Créer le fichier de configuration wordpress dans /home/afup/event.afup.org/web: wp-config.php
6. Jouer le playbook: `ansible-playbook /vagrant/playbooks/web.yml`
