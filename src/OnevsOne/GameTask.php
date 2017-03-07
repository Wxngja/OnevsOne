<?php

namespace OnevsOne;

use pocketmine\scheduler\PluginTask;
use OnevsOne\Main;

class GameTask extends PluginTask{

  const TIME = 10;

  public $time;
  
  public $arena;

  public function __construct(Main $owner, $arena){
    parent::__construct($owner);
    $this->owner = $owner;
    $this->time = self::TIME;
    $this->arena = $arena;
  }

  public function onRun($currentTick){
    $this->time--;
    
    if($this->time > 0 && $this->time <= 10){
      $this->owner->getServer()->broadcastPopup("§e".$this->time, $this->owner->players[$this->arena]);
    }elseif($this->time == 0){
      $this->owner->launchDuel($this->arena);
      $this->owner->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
  }
}