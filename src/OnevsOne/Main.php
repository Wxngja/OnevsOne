<?php

namespace OnevsOne;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\item\Item;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerQuitEvent;
use OnevsOne\GameTask;

class Main extends PluginBase implements Listener{
  
  const PREFIX = "1vs1";
  
  public $game = array();
  
  public $players = array();
  
  public $config;

  public function onEnable(){
    @mkdir($this->getDataFolder());
    $this->config = new Config($this->getDataFolder()."arenas.yml", Config::YAML, array());
    $this->saveDefaultConfig();
    $this->getServer()->getPluginManager()->registerEvents($this, $this);
    $this->getLogger()->info(self::PREFIX." by LCraftPE Enabled...");
  }
  
  public function setSign($arena){
    $game = $this->config->getAll();
    $tile = $this->getServer()->getLevelByName($game[$arena]["sign"][3])->getTile(new Vector3($game[$arena]["sign"][0], $game[$arena]["sign"][1], $game[$arena]["sign"][2]));
    if(!isset($this->game[$arena]["statut"])) $this->game[$arena]["statut"] = 1;
    if($this->game[$arena]["statut"] == 1 || $this->game[$arena]["statut"] == 2){
      $tile->setText("§l1vs1", "§r".count($this->players[$arena])."/2", "§a[Join]", "§r".$arena);
    }else{
      $tile->setText("§l1vs1", "§r".count($this->players[$arena])."/2", "§6[Running]", "§r".$arena);
    }
  }
  
  public function prepareToFight($player){
    $player->getInventory()->clearAll();
    $player->getInventory()->setHelmet(Item::get("298"));
    $player->getInventory()->setChestplate(Item::get("299"));
    $player->getInventory()->setLeggings(Item::get("300"));
    $player->getInventory()->setBoots(Item::get("301"));
    $player->getInventory()->addItem(Item::get(268, 0, 1));
    $player->setHealth(20);
  }
  
  public function launchDuel($arena){
    if(count($this->players[$arena]) == 2){
      $this->game[$arena][$this->players[$arena][0]->getName()] = $this->players[$arena][1];
      $this->game[$arena][$this->players[$arena][1]->getName()] = $this->players[$arena][0];
      $this->players[$arena][1]->sendMessage("§6§l1vs1 §r§7Vous affrontez §f".$this->players[$arena][0]->getName());
      $this->players[$arena][0]->sendMessage("§6§l1vs1 §r§7Vous affrontez §f".$this->players[$arena][1]->getName());
      $this->prepareToFight($this->players[$arena][0]);
      $this->prepareToFight($this->players[$arena][1]);
      $this->game[$arena]["statut"] = 3;
      $this->setSign($arena);
    }else{
      $this->getServer()->broadcastMessage("§cIl n y a pas assez de joueurs.", $this->players[$this->arena]);
      $this->getServer()->loadLevel($this->getServer()->getDefaultLevel()->getName());
      foreach($this->players[$arena] as $player){
        unset($this->players[$arena][array_search($player, $this->players[$arena])], $this->players[$player->getName()]);
        $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
      }
      $this->getServer()->getScheduler()->cancelTask($this->game[$arena]["task"]);
      unset($this->game[$arena]["task"]);
      $this->game[$arena]["statut"] = 1;
      $this->setSign($arena);
    }
  }
  
  public function setWin($player, $loser, $arena){
    $this->getServer()->broadcastMessage("§6§l1vs1 §r§c".$player->getName()." §7a tué en 1vs1 §f".$loser->getName()."§7 dans l'arene §e".$arena);
    unset($this->players[$arena][array_search($player, $this->players[$arena])], $this->game[$arena][$player->getName()], $this->players[$player->getName()]);
    $player->getInventory()->clearAll();
    $player->setHealth(20);
    $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
    $this->game[$arena]["statut"] = 1;
    $this->setSign($arena);
  }
  
  public function onCommand(CommandSender $sender, Command $command, $label, array $args){
    if($command->getName() == "duel"){
      if($sender instanceof Player){
        if($sender->isOp()){
          if(count($args) > 1){
            $game = $this->config->getAll();
            if($args[0] == "create"){
              if(isset($args[1])){
                $arena = $args[1];
                $game[$arena] = array();
                $this->config->setAll($game);
                $this->config->save();
                $sender->sendMessage("§6§l1vs1 §r§7Vous avez défini l'arène §e".$arena);
              }
            }elseif($args[0] == "setspawns"){
              if(count($args) == 3){
                if(isset($game[$args[1]])){
                  $arena = $args[1];
                  if($args[2] == "1"){
                    $game[$arena]["1"] = array(round($sender->getX(), 0), round($sender->getY(), 0), round($sender->getZ(), 0), $sender->getLevel()->getName());
                    $this->config->setAll($game);
                    $this->config->save();
                    $sender->sendMessage("§l§61vs1 §r§7Vous avez défini le §aspawn 1");
                  }elseif($args[2] == "2"){
                    if(isset($game[$arena]["1"])){
                      $game[$arena]["2"] = array(round($sender->getX(), 0), round($sender->getY(), 0), round($sender->getZ(), 0), $game[$arena]["1"][3]);
                      $this->config->setAll($game);
                      $this->config->save();
                      $sender->sendMessage("§6§l1vs1 §r§7Vous avez défini le §aspawn 2");
                    }else{
                      $sender->sendMessage("§cVous devez d'abord définir le spawn 1.");
                    }
                  }else{
                    $sender->sendMessage("§cErreur d'arguments: essaie /duel {setspawns} {1/2}");
                  }
                }else{
                  $sender->sendMessage("§cVous devez d'abord définir l'arène ".$args[1]);
                }
              }else{
                $sender->sendMessage("§cErreur d'arguments: essaie /duel {setspawns} {arena} {1/2}");
              }
            }else{
              $sender->sendMessage("§cErreur d'arguments: essaie /duel {create} {arena} ou /duel {setspawns} {arena} {1/2}");
            }
          }else{
            $sender->sendMessage("§cErreur d'arguments: essaie /duel {create} {arena} ou /duel {setspawns} {arena} {1/2}");
          }
        }else{
          $sender->sendMessage("§cVous n'avez pas la permission d'éxécuter cette commande.");
        }
      }
    }
  }
  
  public function onSignChange(SignChangeEvent $event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $game = $this->config->getAll();
    if($player->isOp() && $event->getLine(0) == "[GAME]"){
      if(isset($game[$event->getLine(1)])){
        $arena = $event->getLine(1);
        if(!isset($this->players[$arena])) $this->players[$arena] = array();
        $game[$arena]["sign"] = array($block->getX(), $block->getY(), $block->getZ(), $player->getLevel()->getName());
        $this->config->setAll($game);
        $this->config->save();
        $event->setLine(0, "§l1vs1");
        $event->setLine(1, "§r".count($this->players[$arena])."/2");
        $event->setLine(2, "§a[Join]");
        $event->setLine(3, "§r".$arena);
        $player->sendMessage("§6§l1vs1 §r§7Vous avez défini le panneau pour rejoindre l'arène §e".$arena);
      }else{
        $event->setLine(0, "***");
        $event->setLine(1, "ERREUR");
        $event->setLine(2, "***");
        $event->setLine(3, "");
        $player->sendMessage("§cVous devez d'abord définir l'arène ".$event->getLine(2));
      }
    }
  }
  
  public function onPlayerInteractEvent(PlayerInteractEvent $event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    if($block->getID() == 323 || $block->getID() == 63 || $block->getID() == 68){
      $sign = $player->getLevel()->getTile($block);
      if(!$sign instanceof Sign) return;
      $game = $this->config->getAll();
      foreach($game as $arena => $value){
        if(strpos($sign->getText()[3], $arena)){
          if(!isset($this->players[$arena])) $this->players[$arena] = array();
          if(!isset($this->game[$arena]["statut"])) $this->game[$arena]["statut"] = 1;
          if($this->game[$arena]["statut"] == 3 || count($this->players[$arena]) == 2){
            $player->sendMessage("§cLa partie a deja commencée.");
            $event->setCancelled(true);
          }else{
            $this->getServer()->loadLevel($game[$arena]["1"][3]);
            if(count($this->players[$arena]) == 1){
              $player->teleport(new Position($game[$arena]["1"][0], $game[$arena]["1"][1], $game[$arena]["1"][2], $this->getServer()->getLevelByName($game[$arena]["1"][3])));
            }else{
              $player->teleport(new Position($game[$arena]["2"][0], $game[$arena]["2"][1], $game[$arena]["2"][2], $this->getServer()->getLevelByName($game[$arena]["2"][3])));
            }
            array_push($this->players[$arena], $player);
            $this->players[$player->getName()] = $arena;
            $this->getServer()->broadcastMessage("§6§l1vs1 §r§f".$player->getName()." §7a rejoint la partie §a(".count($this->players[$arena])."/2)", $this->players[$arena]);
            $this->setSign($arena);
            if($this->game[$arena]["statut"] == 1 && count($this->players[$arena]) == 2){
              $this->game[$arena]["statut"] = 2;
              $scheduler = new GameTask($this, $arena);
              $scheduler->setHandler($this->getServer()->getScheduler()->scheduleRepeatingTask($scheduler, 20));
              $this->game[$arena]["task"] = $scheduler->getTaskId();
            }
          }
        }
        break;
      }
    }
  } 
  
  public function onPlayerMove(PlayerMoveEvent $event){
    $player = $event->getPlayer();
    if(isset($this->players[$player->getName()])){
      $arena = $this->players[$player->getName()]; 
      if($this->game[$arena]["statut"] == 1 || $this->game[$arena]["statut"] == 2) $event->setCancelled(true);
    }
  }
  
  public function onEntityDamage(EntityDamageEvent $event){
    $player = $event->getEntity();
    if($player instanceof Player){
      if(isset($this->players[$player->getName()])){
        $arena = $this->players[$player->getName()]; 
        if($this->game[$arena]["statut"] == 1 || $this->game[$arena]["statut"] == 2) $event->setCancelled(true);
      }
    }
  }
  
  public function onPlayerDeath(PlayerDeathEvent $event){
    $player = $event->getEntity();
    if($player instanceof Player){
      if(isset($this->players[$player->getName()])){
        $arena = $this->players[$player->getName()];
        $winner = $this->game[$arena][$player->getName()];
        unset($this->players[$arena][array_search($player, $this->players[$arena])], $this->game[$arena][$player->getName()], $this->players[$player->getName()]);
        $this->setWin($winner, $player, $arena);
        $event->setDrops([]);
        $event->setDeathMessage("");
      }
    }
  }
  
  public function onBlockBreak(BlockBreakEvent $event){
    if(isset($this->players[$event->getPlayer()->getName()])) $event->setCancelled(true);
  }

  public function onBlockPlace(BlockPlaceEvent $event){
    if(isset($this->players[$event->getPlayer()->getName()])) $event->setCancelled(true);
  }
  
  public function onPlayerQuit(PlayerQuitEvent $event){
    $player = $event->getPlayer();
    if(isset($this->players[$player->getName()])){
      $arena = $this->players[$player->getName()];
      $winner = $this->game[$arena][$player->getName()];
      unset($this->players[$arena][array_search($player, $this->players[$arena])], $this->game[$arena][$player->getName()], $this->players[$player->getName()]);
      $this->setWin($winner, $player, $arena);
    }
  }
  
  public function onDisable(){
    $game = $this->config->getAll();
    foreach($game as $arena => $value) $this->setSign($arena);
  }
}
