<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyFactions\commands\subcommands;

use DaPigGuy\PiggyFactions\libs\CortexPE\Commando\args\BaseArgument;
use DaPigGuy\PiggyFactions\libs\CortexPE\Commando\args\BooleanArgument;
use DaPigGuy\PiggyFactions\libs\CortexPE\Commando\args\FloatArgument;
use DaPigGuy\PiggyFactions\libs\CortexPE\Commando\args\IntegerArgument;
use DaPigGuy\PiggyFactions\libs\CortexPE\Commando\args\StringEnumArgument;
use DaPigGuy\PiggyFactions\libs\CortexPE\Commando\BaseSubCommand;
use DaPigGuy\PiggyFactions\factions\Faction;
use DaPigGuy\PiggyFactions\permissions\PermissionFactory;
use DaPigGuy\PiggyFactions\PiggyFactions;
use DaPigGuy\PiggyFactions\players\FactionsPlayer;
use DaPigGuy\PiggyFactions\utils\PiggyArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use DaPigGuy\PiggyFactions\libs\Vecnavium\FormsUI\CustomForm;

abstract class FactionSubCommand extends BaseSubCommand
{
    /** @var PiggyFactions */
    protected $plugin;

    protected bool $requiresPlayer = true;
    protected bool $requiresFaction = true;
    protected bool $factionPermission = true;

    protected ?string $parentNode = null;

    public function __construct(PiggyFactions $plugin, string $name, string $description = "", array $aliases = [])
    {
        $permissionPrefix = "piggyfactions.command.faction.";
        if ($this->parentNode !== null) $permissionPrefix = $permissionPrefix . $this->parentNode . ".";
        $this->setPermission($permissionPrefix . $name);
        parent::__construct($plugin, $name, $description, $aliases);
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player && $this->requiresPlayer) {
            $sender->sendMessage(TextFormat::RED . "Please use this command in-game.");
            return;
        }

        $member = $sender instanceof Player ? $this->plugin->getPlayerManager()->getPlayer($sender) : null;
        $faction = $member?->getFaction();

        if ($this->requiresFaction && $this->requiresPlayer) {
            if ($faction === null) {
                $member->sendMessage("commands.not-in-faction");
                return;
            }

            if (!$this->factionPermission) {
                $parent = $this->getParent();
                $permission = $this->getName();
                while ($parent instanceof BaseSubCommand) {
                    $permission = $parent->getName();
                    $parent = $parent->getParent();
                }
                if (PermissionFactory::getPermission($permission) !== null) {
                    if (!$faction->hasPermission($member, $permission)) {
                        $member->sendMessage("commands.no-permission");
                        return;
                    }
                }
            }
        }

        foreach ($this->getArgumentList() as $arguments) {
            /** @var PiggyArgument $argument */
            foreach ($arguments as $argument) {
                if (!$argument->getWrappedArgument()->isOptional() && !isset($args[$argument->getName()])) {
                    if ($sender instanceof Player) {
                        $this->onFormRun($sender, $faction, $member, $aliasUsed, $args);
                    } else {
                        $this->sendUsage();
                    }
                    return;
                }
            }
        }

        if ($this->requiresPlayer && $sender instanceof Player) {
            $this->onNormalRun($sender, $faction, $member, $aliasUsed, $args);
        } else {
            $this->onBasicRun($sender, $args);
        }
    }

    public function onBasicRun(CommandSender $sender, array $args): void
    {
    }

    public function onNormalRun(Player $sender, ?Faction $faction, FactionsPlayer $member, string $aliasUsed, array $args): void
    {
    }

    public function onFormRun(Player $sender, ?Faction $faction, FactionsPlayer $member, string $aliasUsed, array $args): void
    {
        $commandArguments = [];
        $enums = [];
        foreach ($this->getArgumentList() as $position => $arguments) {
            /** @var PiggyArgument $argument */
            foreach ($arguments as $argument) {
                $argument = $argument->getWrappedArgument();
                $commandArguments[$position] = $argument;
                if ($argument instanceof StringEnumArgument) $enums[$position] = $argument->getEnumValues();
            }
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($enums): void {
            if ($data !== null) {
                $args = [];
                foreach ($this->getArgumentList() as $position => $arguments) {
                    if (!isset($data[$position])) continue;
                    /** @var PiggyArgument $argument */
                    foreach ($arguments as $argument) {
                        $wrappedArgument = $argument->getWrappedArgument();
                        if ($wrappedArgument instanceof StringEnumArgument && !$wrappedArgument instanceof BooleanArgument) {
                            $args[$argument->getName()] = $enums[$position][$data[$position]];
                        } elseif ($wrappedArgument instanceof IntegerArgument) {
                            $args[$argument->getName()] = (int)$data[$position];
                        } elseif ($wrappedArgument instanceof FloatArgument) {
                            $args[$argument->getName()] = (float)$data[$position];
                        } else {
                            $args[$argument->getName()] = $data[$position];
                        }
                    }
                }
                $this->onRun($player, $this->getName(), $args);
            }
        });
        $form->setTitle("/f " . $this->getName());
        foreach ($commandArguments as $argument) {
            if ($argument instanceof BooleanArgument) {
                $form->addToggle(ucfirst($argument->getName()), $args[$argument->getName()] ?? null);
            } elseif ($argument instanceof StringEnumArgument) {
                $form->addDropdown(ucfirst($argument->getName()), $argument->getEnumValues(), (int)(array_search($args[$argument->getName()] ?? "", $argument->getEnumValues())));
            } else {
                $form->addInput(ucfirst($argument->getName()), "", $args[$argument->getName()] ?? null);
            }
        }
        $sender->sendForm($form);
    }

    public function registerArgument(int $position, BaseArgument $argument): void
    {
        parent::registerArgument($position, new PiggyArgument($argument));
    }

    protected function prepare(): void
    {
    }
}