<?php
#  _    _ _    _  _____ 
# | |  | | |  | |/ ____|
# | |  | | |__| | |     
# | |  | |  __  | |     
# | |__| | |  | | |____ 
#  \____/|_|  |_|\_____|
# The most customisable UHC plugin for Minecraft PE !
namespace Ad5001\UHC ; 
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\Server;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\level\Level;
use pocketmine\plugin\Plugin;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat as C;
use pocketmine\Player;



use Ad5001\UHC\UHCWorld;
use Ad5001\UHC\task\StopResTask;
use Ad5001\UHC\Main;
use Ad5001\UHC\event\GameStartEvent;
use Ad5001\UHC\event\GameStopEvent;




class UHCGame implements Listener{
    public function __construct(Plugin $plugin, UHCWorld $world) {
        $this->m = $plugin;
        $this->world = $world;
        $world->getLevel()->setTime(0);
        $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
        $event = new GameStartEvent($this, $world, $world->getLevel()->getPlayers());
        $this->m->getServer()->getPluginManager()->callEvent($event);
        $this->cancelled = false;
        $this->kills = [];
        if($event->isCancelled()) {
            $this->cancelled = true;
        } else {
            $radius = $world->radius;
            foreach($world->getLevel()->getPlayers() as $player) {
                $player->getInventory()->clearAll();
                $player->setGamemode(0);
                for($e = 1; $e < 24; $e++) {$player->removeEffect($e);}
                $x = rand($radius + $world->getLevel()->getSpawnLocation()->x, $world->getLevel()->getSpawnLocation()->x - $radius);
                $z = rand($radius + $world->getLevel()->getSpawnLocation()->z, $world->getLevel()->getSpawnLocation()->z - $radius);
                $pos = new Vector3($x, 128, $z);
                $player->teleport($pos);
                $effect = \pocketmine\entity\Effect::getEffect(11);
                $effect->setDuration(30*20);
                $effect->setAmplifier(99);
                $effect->setVisible(false);
                $player->addEffect($effect);
                $this->m->getServer()->getScheduler()->scheduleDelayedTask(new StopResTask($this->m, $this->world->getPlayers()), 30*20);
                $player->sendMessage(Main::PREFIX . C::GREEN . "Game started ! Good luck {$player->getName()} !");
            }
        }
    }
    
    
    public function onHeal(EntityRegainHealthEvent $event) {
        if($event->getEntity() instanceof Player and $event->getRegainReason() === EntityRegainHealthEvent::CAUSE_SATURATION and $event->getEntity()->getLevel()->getName() === $this->world->getLevel()->getName()) { // if player is playing
            $event->setCancelled();
        }
    }


    public function onGameStart(\Ad5001\UHC\event\GameStartEvent $event) {}


    public function onGameStop(\Ad5001\UHC\event\GameStopEvent $event) {}
    
    
    public function onRespawn(PlayerRespawnEvent $event) {
        if(isset($this->respawn[$event->getPlayer()->getName()]) and !$this->cancelled) {
            $event->getPlayer()->setGamemode(3);
            unset($this->respawn[$event->getPlayer()->getName()]);
        }
    }
    
    
    public function onPlayerQuit(PlayerQuitEvent $event) {
        if($event->getPlayer()->getLevel()->getName() === $this->world->getLevel()->getName()) {
            $this->m->quit[$event->getPlayer()->getName()] = "{$event->getPlayer()->x}/{$event->getPlayer()->y}/{$event->getPlayer()->z}/{$event->getPlayer()->getLevel()->getName()}/";
        }
    }
    
    
    public function onPlayerDeath(PlayerDeathEvent $event) {
        if($event->getPlayer()->getLevel()->getName() === $this->world->getName() and !$this->cancelled) {
            foreach($event->getPlayer()->getLevel()->getPlayers() as $p) {
                $p->sendMessage(Main::PREFIX . C::YELLOW . $event->getPlayer()->getName() . " died. " . (count($this->world->getLevel()->getPlayers()) - 1) . " players left !");
            }
            $this->respawn[$event->getPlayer()->getName()] = true;
            $pls = [];
            foreach($event->getPlayer()->getLevel()->getPlayers() as $pl) {
                array_push($pls, $pl);
            }
            $cause = $event->getEntity()->getLastDamageCause();
            if($cause instanceof \pocketmine\event\entity\EntityDamageByEntityEvent){
                $killer = $cause->getDamager();
                if($killer instanceof Player){
                    if(isset($this->kills[$killer->getName()])) {
                        $this->kills[$killer->getName()]++;
                    } else {
                        $this->kills[$killer->getName()] = 1;
                    }
                } else {
                    if(isset($this->kills[C::GREEN . "P" . C::BLUE . "v" . C::RED . "E"])) {
                        $this->kills[C::GREEN . "P" . C::BLUE . "v" . C::RED . "E"]++;
                    } else {
                        $this->kills[C::GREEN . "P" . C::BLUE . "v" . C::RED . "E"] = 1;
                    }
                }
            } else {
                if(isset($this->kills[C::GREEN . "P" . C::BLUE . "v" . C::RED . "E"])) {
                    $this->kills[C::GREEN . "P" . C::BLUE . "v" . C::RED . "E"]++;
                } else {
                    $this->kills[C::GREEN . "P" . C::BLUE . "v" . C::RED . "E"] = 1;
                }
            }
            if(count($pls) == 2) {
                foreach($pls as $p) {
                    if($p !== $event->getPlayer()) {
                        $this->stop($p);
                    }
                }
            } elseif(count($pls) == 1) {
                $this->stop($event->getPlayer());
            }
        }
    }
    
    
    public function stop(Player $winner) {
        $this->m->getServer()->getPluginManager()->callEvent($ev = new GameStopEvent($this, $this->world, $winner));
        if(!$ev->cancelled) {
            foreach($winner->getLevel()->getPlayers() as $player) {
                $player->sendMessage(Main::PREFIX . C::YELLOW . $winner->getName() . " won the game ! Teleporting back to lobby...");
                $player->teleport($this->m->getServer()->getLevelByName($this->m->getConfig()->get("LobbyWorld"))->getSafeSpawn());
                $this->m->UHCManager->stopUHC($this->world->getLevel(), $winner);
            }
        }
    }
    
    
    public function getPlayers() {
        return $this->world->getPlayers();
    }
    
    
    public function onPlayerChat(PlayerChatEvent $event) {
        if($event->getPlayer()->getLevel()->getName() === $this->world->getLevel()->getName() and $event->getPlayer()->getGamemode() === 3) {
            if($event->getPlayer()->isSpectator()) {
                foreach($this->world->getLevel()->getPlayer() as $player) {
                    $player->sendMessage(C::GRAY . "[SPECTATOR] {$event->getPlayer()->getName()} > " . $event->getMessage());
                    
                }
                $event->setCancelled(true);
            }
        }
    }
    
    /*
    Will be useful for scenarios:
    @param player
    */
    public function getKills(Player $player) {
        if(isset($this->kills[$player->getName()])) {
            return $this->kills[$player->getName()];
        } else {
            return null;
        }
    }
    /*
    Will be useful for scenarios too:
    @param player
    */
    public function addKills(Player $player, int $count) {
        if(isset($this->kills[$player->getName()])) {
            $this->kills[$player->getName()] += $count;
        } else {
            $this->kills[$player->getName()] = $count;
        }
        return true;
    }
}