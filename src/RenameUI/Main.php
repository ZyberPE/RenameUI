<?php

declare(strict_types=1);

namespace RenameUI;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\Durable;

use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;

class Main extends PluginBase {

    public function onEnable(): void {
        $this->saveDefaultConfig();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player) return true;

        if($command->getName() === "rename"){
            $this->openMainUI($sender);
        }
        return true;
    }

    /**
     * First UI (Rename / Close)
     */
    private function openMainUI(Player $player): void {
        $config = $this->getConfig();

        $form = new SimpleForm(function(Player $player, ?int $data){
            if($data === null) return;

            switch($data){
                case 0:
                    $this->openRenameUI($player);
                    break;

                case 1:
                    // Close button
                    return;
            }
        });

        $form->setTitle($config->get("messages")["title"]);
        $form->setContent($config->get("messages")["content"]);

        $form->addButton($config->get("messages")["rename-button"]);
        $form->addButton($config->get("messages")["close-button"]);

        $player->sendForm($form);
    }

    /**
     * Rename Form (dropdown + input)
     */
    private function openRenameUI(Player $player): void {
        $config = $this->getConfig();

        $items = [];
        $names = [];

        foreach($player->getInventory()->getContents() as $slot => $item){
            if($item instanceof Durable){
                $items[$slot] = $item;
                $names[] = $item->getName();
            }
        }

        if(empty($items)){
            $player->sendMessage("§cNo valid items found!");
            return;
        }

        $form = new CustomForm(function(Player $player, ?array $data) use ($items){
            if($data === null) return;

            $slotIndex = array_keys($items)[$data[0]] ?? null;
            $newName = $data[1];

            if($slotIndex === null || trim($newName) === ""){
                $player->sendMessage($this->getConfig()->get("messages")["invalid-item"]);
                return;
            }

            $this->openConfirmUI($player, $slotIndex, $newName);
        });

        $form->setTitle($config->get("messages")["title"]);
        $form->addDropdown($config->get("messages")["dropdown"], $names);
        $form->addInput($config->get("messages")["input"]);

        $player->sendForm($form);
    }

    /**
     * Confirmation UI
     */
    private function openConfirmUI(Player $player, int $slot, string $newName): void {
        $config = $this->getConfig();
        $xpCost = $config->get("settings")["xp-cost"];

        $content = str_replace("{new_name}", $newName, $config->get("messages")["confirm-content"]);

        $form = new ModalForm(function(Player $player, bool $choice) use ($slot, $newName, $xpCost){
            if(!$choice) return;

            $config = $this->getConfig();

            if(!$player->hasPermission("renameui.use")){
                $player->sendMessage($config->get("messages")["no-permission"]);
                return;
            }

            $bypass = $player->hasPermission("renameui.bypass");

            if(!$bypass && $player->getXpManager()->getXpLevel() < $xpCost){
                $msg = str_replace("{xp}", (string)$xpCost, $config->get("messages")["not-enough-xp"]);
                $player->sendMessage($msg);
                return;
            }

            $item = $player->getInventory()->getItem($slot);

            if($item->isNull()){
                $player->sendMessage($config->get("messages")["invalid-item"]);
                return;
            }

            $item->setCustomName($newName);
            $player->getInventory()->setItem($slot, $item);

            if(!$bypass){
                $player->getXpManager()->subtractXpLevels($xpCost);
            }

            $player->sendMessage($config->get("messages")["success"]);
        });

        $form->setTitle($config->get("messages")["confirm-title"]);
        $form->setContent($content);
        $form->setButton1($config->get("messages")["confirm-button"]);
        $form->setButton2($config->get("messages")["cancel-button"]);

        $player->sendForm($form);
    }
}
