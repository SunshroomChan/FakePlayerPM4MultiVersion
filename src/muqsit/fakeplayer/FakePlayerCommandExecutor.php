<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use JsonException;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use ReflectionProperty;

final class FakePlayerCommandExecutor implements CommandExecutor{

	public function __construct(
		private Loader $plugin
	){}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(isset($args[0])){
			switch($args[0]){
				case "tpall":
					if($sender instanceof Player){
						$pos = $sender->getPosition();
						foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
							if($this->plugin->isFakePlayer($player)){
								$player->teleport($pos->add(8 * (lcg_value() * 2 - 1), 0.0, 8 * (lcg_value() * 2 - 1)));
							}
						}
					}
					return true;
				default:
					if(isset($args[1])){
						$player = $this->plugin->getServer()->getPlayerByPrefix($args[0]);
						if($player !== null){
							if($this->plugin->isFakePlayer($player)){
								/** @var FakePlayerNetworkSession $session */
								$session = $player->getNetworkSession();
								switch($args[1]){
									case "chat":
										if(isset($args[2])){
											$chat = implode(" ", array_slice($args, 2)); // TODO: use a method that complies with arg containing spaces

											$session->registerSpecificPacketListener(TextPacket::class, $listener = new ClosureFakePlayerPacketListener(static function(ClientboundPacket $packet, NetworkSession $session) use($sender) : void{
												/** @var TextPacket $packet */
												if($packet->type !== TextPacket::TYPE_JUKEBOX_POPUP && $packet->type !== TextPacket::TYPE_POPUP && $packet->type !== TextPacket::TYPE_TIP){
													$sender->sendMessage($packet->message);
												}
											}));
											$player->chat($chat);
											$session->unregisterSpecificPacketListener(TextPacket::class, $listener);
										}else{
											$sender->sendMessage(TextFormat::RED . "Usage: /" . $label . " " . $player->getName() . " " . $args[1] . " <...chat>");
										}
										return true;
									case "form":
										if(isset($args[2]) && isset($args[3])){
											$_formIdCounter = new ReflectionProperty(Player::class, "formIdCounter");
											$_formIdCounter->setAccessible(true);
											$form_id = $_formIdCounter->getValue($player) - 1;
											if($args[2] === "button"){
												$player->onFormSubmit($form_id, (int) $args[3]);
												return true;
											}
											if($args[2] === "raw"){
												try{
													$response = json_decode(implode(" ", array_slice($args, 3)), false, 512, JSON_THROW_ON_ERROR);
												}catch(JsonException $e){
													$sender->sendMessage(TextFormat::RED . "Failed to parse JSON: {$e->getMessage()}");
													return true;
												}
												$player->onFormSubmit($form_id, $response);
												return true;
											}
										}
										$sender->sendMessage(TextFormat::RED . "Usage: /" . $label . " " . $player->getName() . " " . $args[1] . " button <#>");
										$sender->sendMessage(TextFormat::RED . "Usage: /" . $label . " " . $player->getName() . " " . $args[1] . " raw <responseJson>");
										return true;
									case "interact":
										$target_block = $player->getTargetBlock(5);
										$item_in_hand = $player->getInventory()->getItemInHand();
										if($target_block !== null){
											$player->interactBlock($target_block->getPosition(), $player->getHorizontalFacing(), new Vector3(0, 0, 0));
											$sender->sendMessage(TextFormat::GRAY . "{$player->getName()} is interacting with {$target_block->getName()} at {$target_block->getPosition()->asVector3()} using {$item_in_hand}" . TextFormat::RESET . TextFormat::GRAY . ".");
										}else{
											$player->useHeldItem();
											$sender->sendMessage(TextFormat::GRAY . "{$player->getName()} is interacting using {$item_in_hand}" . TextFormat::RESET . TextFormat::GRAY . ".");
										}
										return true;
								}
							}else{
								$sender->sendMessage(TextFormat::RED . $player->getName() . " is NOT a fake player!");
								return true;
							}
						}else{
							$sender->sendMessage(TextFormat::RED . $args[0] . " is NOT online!");
							return true;
						}
					}
					break;
			}
		}

		$sender->sendMessage(
			TextFormat::AQUA . TextFormat::BOLD . $this->plugin->getName() . " Commands" . TextFormat::RESET . TextFormat::EOL .
			TextFormat::AQUA . "/" . $label . " tpall" . TextFormat::GRAY . " - Teleport all fake players to you" . TextFormat::EOL .
			TextFormat::AQUA . "/" . $label . " <player> chat <...chat>" . TextFormat::GRAY . " - Chat on behalf of a fake player" . TextFormat::EOL .
			TextFormat::AQUA . "/" . $label . " <player> form " . TextFormat::GRAY . " - Submit a form on behalf of a fake player"
		);
		return true;
	}
}