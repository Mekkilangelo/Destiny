<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyFactions\commands\subcommands\claims;

use DaPigGuy\PiggyFactions\commands\subcommands\FactionSubCommand;
use DaPigGuy\PiggyFactions\factions\Faction;
use DaPigGuy\PiggyFactions\players\FactionsPlayer;
use pocketmine\player\Player;

class BorderSubCommand extends FactionSubCommand
{
    protected bool $requiresFaction = false;

    public function onNormalRun(Player $sender, ?Faction $faction, FactionsPlayer $member, string $aliasUsed, array $args): void
    {
        $member->setCanSeeBorders(!$member->canSeeBorders());
        
        if ($member->canSeeBorders()) {
            $member->sendMessage("commands.border.enabled");
        } else {
            $member->sendMessage("commands.border.disabled");
        }
    }
}
