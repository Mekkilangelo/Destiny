# Nouvelle fonctionnalité: Visualisation des bordures de territoire

## Commande `/f border`

Cette nouvelle commande permet d'activer/désactiver la visualisation des bordures des territoires de factions.

### Utilisation
```
/f border
```

### Fonctionnalités

1. **Activation/Désactivation**: La commande bascule entre l'activation et la désactivation du système de visualisation.

2. **Couleurs selon les relations**:
   - **Vert**: Territoire de votre propre faction
   - **Bleu**: Territoire d'une faction alliée
   - **Jaune**: Territoire d'une faction en trêve
   - **Rouge**: Territoire d'une faction ennemie
   - **Orange**: Territoire d'une faction neutre
   - **Blanc**: Pour les joueurs sans faction

3. **Performance optimisée**: 
   - Affichage uniquement dans un rayon de 8 chunks autour du joueur
   - Particules affichées seulement aux vraies bordures (pas entre deux chunks de la même faction)
   - Rafraîchissement toutes les secondes (20 ticks)

4. **Détection intelligente des bordures**: 
   - Le système détecte automatiquement les bordures réelles des territoires
   - Seules les bordures extérieures sont affichées
   - Les particules sont espacées de 2 blocs pour une meilleure visibilité

### Différences avec `/f seechunk`

- `/f seechunk`: Affiche les bordures du chunk actuel où se trouve le joueur
- `/f border`: Affiche les bordures de tous les territoires de factions dans la zone

### Permissions

- `piggyfactions.command.faction.border` (défaut: true)

### Messages de langue

Les messages sont disponibles en anglais et en français :
- `commands.border.enabled`: Message affiché quand la visualisation est activée
- `commands.border.disabled`: Message affiché quand la visualisation est désactivée

Cette fonctionnalité améliore grandement l'expérience de jeu en permettant aux joueurs de voir clairement les limites des territoires des différentes factions et leurs relations diplomatiques.
