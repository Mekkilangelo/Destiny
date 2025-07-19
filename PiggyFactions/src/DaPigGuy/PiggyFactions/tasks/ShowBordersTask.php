<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyFactions\tasks;

use DaPigGuy\PiggyFactions\PiggyFactions;
use DaPigGuy\PiggyFactions\utils\Relations;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\world\format\Chunk;
use pocketmine\world\particle\DustParticle;
use pocketmine\color\Color;

class ShowBordersTask extends Task
{
    public function __construct(private PiggyFactions $plugin)
    {
    }

    public function onRun(): void
    {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $member = $this->plugin->getPlayerManager()->getPlayer($player);
            if ($member === null || !$member->canSeeBorders()) {
                continue;
            }

            $playerWorld = $player->getWorld();
            $playerPosition = $player->getPosition();
            
            // Calculer les bordures à afficher (seulement les chunks près du joueur pour les performances)
            $playerChunkX = $playerPosition->getFloorX() >> Chunk::COORD_BIT_SIZE;
            $playerChunkZ = $playerPosition->getFloorZ() >> Chunk::COORD_BIT_SIZE;
            $renderDistance = 8; // Affiche les bordures dans un rayon de 8 chunks

            // Récupérer tous les claims dans la zone de rendu
            for ($x = $playerChunkX - $renderDistance; $x <= $playerChunkX + $renderDistance; $x++) {
                for ($z = $playerChunkZ - $renderDistance; $z <= $playerChunkZ + $renderDistance; $z++) {
                    $claim = $this->plugin->getClaimsManager()->getClaim($x, $z, $playerWorld->getFolderName());
                    if ($claim !== null) {
                        // Calculer les coordonnées du chunk
                        $minX = $x * 16;
                        $maxX = $minX + 15;
                        $minZ = $z * 16;
                        $maxZ = $minZ + 15;

                        // Rendre les bordures de ce chunk
                        $this->renderChunkBorders($player, $claim, $minX, $maxX, $minZ, $maxZ, $member);
                    }
                }
            }
        }
    }

    private function renderChunkBorders($player, $claim, int $minX, int $maxX, int $minZ, int $maxZ, $member): void
    {
        $playerY = $player->getPosition()->y;
        $world = $player->getWorld();
        $chunkX = $claim->getChunkX();
        $chunkZ = $claim->getChunkZ();
        
        // Déterminer la couleur en fonction de la relation
        $claimFaction = $claim->getFaction();
        $playerFaction = $member->getFaction();
        
        if ($claimFaction === null) {
            return; // Ne pas afficher les bordures pour les claims sans faction
        }
        
        $color = $this->getRelationColor($playerFaction, $claimFaction);
        
        // Vérifier chaque côté du chunk pour voir s'il y a une bordure
        $step = 2; // Espacement entre les particules
        
        // Côté Nord (Z-)
        if (!$this->hasAdjacentClaimOfSameFaction($chunkX, $chunkZ - 1, $claimFaction, $world->getFolderName())) {
            for ($x = $minX; $x <= $maxX; $x += $step) {
                $world->addParticle(new Vector3($x + 0.5, $playerY + 1.5, $minZ), new DustParticle($color), [$player]);
            }
        }
        
        // Côté Sud (Z+)
        if (!$this->hasAdjacentClaimOfSameFaction($chunkX, $chunkZ + 1, $claimFaction, $world->getFolderName())) {
            for ($x = $minX; $x <= $maxX; $x += $step) {
                $world->addParticle(new Vector3($x + 0.5, $playerY + 1.5, $maxZ + 1), new DustParticle($color), [$player]);
            }
        }
        
        // Côté Ouest (X-)
        if (!$this->hasAdjacentClaimOfSameFaction($chunkX - 1, $chunkZ, $claimFaction, $world->getFolderName())) {
            for ($z = $minZ; $z <= $maxZ; $z += $step) {
                $world->addParticle(new Vector3($minX, $playerY + 1.5, $z + 0.5), new DustParticle($color), [$player]);
            }
        }
        
        // Côté Est (X+)
        if (!$this->hasAdjacentClaimOfSameFaction($chunkX + 1, $chunkZ, $claimFaction, $world->getFolderName())) {
            for ($z = $minZ; $z <= $maxZ; $z += $step) {
                $world->addParticle(new Vector3($maxX + 1, $playerY + 1.5, $z + 0.5), new DustParticle($color), [$player]);
            }
        }
    }

    private function getRelationColor($playerFaction, $claimFaction): Color
    {
        if ($playerFaction === null) {
            return new Color(255, 255, 255); // Blanc pour les joueurs sans faction
        }
        
        if ($playerFaction === $claimFaction) {
            return new Color(0, 255, 0); // Vert pour sa propre faction
        }
        
        $relation = $playerFaction->getRelation($claimFaction);
        
        return match($relation) {
            Relations::ALLY => new Color(0, 0, 255),    // Bleu pour les alliés
            Relations::TRUCE => new Color(255, 255, 0), // Jaune pour les trêves
            Relations::ENEMY => new Color(255, 0, 0),   // Rouge pour les ennemis
            default => new Color(255, 165, 0)           // Orange pour neutres
        };
    }

    private function hasAdjacentClaimOfSameFaction(int $chunkX, int $chunkZ, $faction, string $worldName): bool
    {
        $adjacentClaim = $this->plugin->getClaimsManager()->getClaim($chunkX, $chunkZ, $worldName);
        return $adjacentClaim !== null && $adjacentClaim->getFaction() === $faction;
    }
}
