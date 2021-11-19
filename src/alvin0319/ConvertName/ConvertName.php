<?php

/*
 * MIT License
 *
 * Copyright (c) 2021 alvin0319 and contributors.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace alvin0319\ConvertName;

use pocketmine\block\Bed as BlockBed;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\item\Bed;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use function array_merge;
use function explode;
use function file_exists;
use function file_get_contents;
use function method_exists;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;

final class ConvertName extends PluginBase{

	protected function onEnable() : void{
		$this->saveDefaultConfig();

		$languageFile = $this->getConfig()->get("language_file");

		if(!file_exists($file = $this->getDataFolder() . $languageFile)){
			$this->getLogger()->critical("Language file doesn't exist for {$languageFile}, Please download the file from https://aka.ms/resourcepacktemplate");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$content = file_get_contents($file);

		$parsedLang = $this->findItem($this->parse($content));

		$this->getScheduler()->scheduleTask(new ClosureTask(function() use ($parsedLang) : void{
			$registeredItems = array_merge(ItemFactory::getInstance()->getAllRegistered(), array_values(BlockFactory::getInstance()->getAllKnownStates()));
			$foundItems = $parsedLang;
			$name = function(object $object) : string{
				if(method_exists($object, "getName")){
					if(str_contains(($name = strtolower($object->getName())), " ")){
						return $this->cleanItemName(str_replace(" ", "_", $name));
					}
					return $this->cleanItemName($name);
				}

				if(method_exists($object, "name")){
					if(str_contains(($name = strtolower($object->name())), " ")){
						return $this->cleanItemName(str_replace(" ", "_", $name));
					}
					return $this->cleanItemName($name);
				}
				return spl_object_hash($object);
			};

			$color_convert = static function($color) use ($name) : string{
				$converted = $name($color);
				return str_replace("_", "", $converted);
			};

			$color_name_convert = static function($color) : int{
				$dyeMap = [
					DyeColor::BLACK()->id() => 16,
					DyeColor::BROWN()->id() => 17,
					DyeColor::BLUE()->id() => 18,
					DyeColor::WHITE()->id() => 19
				];
				return $dyeMap[$color->id()] ?? DyeColorIdMap::getInstance()->toInvertedId($color);
			};

			foreach($registeredItems as $item){
				if($item instanceof Item){
					$refP = new \ReflectionProperty($item, "name");
				}elseif($item instanceof Block){
					$refP = new \ReflectionProperty($item, "fallbackName");
				}else{
					throw new \UnexpectedValueException("Unexpected object " . get_class($item));
				}
				$refP->setAccessible(true);
				$key = "item." . $name($item) . ".name";
				if(isset($foundItems[$key])){
					$refP->setValue($item, $foundItems[$key]);
				}elseif(isset($foundItems[$key = "tile." . $name($item) . ".name"])){
					$refP->setValue($item, $foundItems[$key]);
				}
			}
			foreach(DyeColor::getAll() as $color){
				$key = "item.bed." . $color_convert($color) . ".name";
				if(isset($foundItems[$key])){
					ItemFactory::getInstance()->register(new Bed(new ItemIdentifier(ItemIds::BED, DyeColorIdMap::getInstance()->toId($color)), $foundItems[$key], $color), true);
				}
				$dyeMap = [
					DyeColor::BLACK()->id() => 16,
					DyeColor::BROWN()->id() => 17,
					DyeColor::BLUE()->id() => 18,
					DyeColor::WHITE()->id() => 19
				];
				$key = "item.dye." . $color_convert($color) . ".name";
				if(isset($foundItems[$key])){
					ItemFactory::getInstance()->register(new Dye(new ItemIdentifier(ItemIds::DYE, $dyeMap[$color->id()] ?? DyeColorIdMap::getInstance()->toInvertedId($color)), $foundItems["item.dye." . $color_convert($color) . ".name"], $color), true);
				}

				$block = BlockFactory::getInstance()->get(BlockLegacyIds::BED_BLOCK, DyeColorIdMap::getInstance()->toId($color));
				if($block instanceof BlockBed){
					$color = $block->getColor();
					$key = "tile.bed.name";
					$refP = new \ReflectionProperty(Block::class, "fallbackName");
					$refP->setAccessible(true);
					if(isset($foundItems[$key])){
						$refP->setValue($block, $color_convert($color) . " " . $foundItems[$key]);
						BlockFactory::getInstance()->register($block, true);
					}
				}
			}
			// TODO: more blocks & items

			/*
			$names = [
				PotionTypeIds::WATER => "water",
				PotionTypeIds::MUNDANE => "mundane",
				PotionTypeIds::LONG_MUNDANE => "mundane",
				PotionTypeIds::THICK => "thick",
				PotionTypeIds::AWKWARD => "awkward",
				PotionTypeIds::NIGHT_VISION => "night_vision",
				PotionTypeIds::LONG_NIGHT_VISION => "night_vision",
				PotionTypeIds::INVISIBILITY => "invisibility",
				PotionTypeIds::LONG_INVISIBILITY => "invisibility",
				PotionTypeIds::LEAPING => "leaping",
				PotionTypeIds::LONG_LEAPING => "leaping",
				PotionTypeIds::STRONG_LEAPING => "leaping",
				PotionTypeIds::FIRE_RESISTANCE => "fire_resistance",
				PotionTypeIds::LONG_FIRE_RESISTANCE => "fire_resistance",
				PotionTypeIds::SWIFTNESS => "swiftness",
				PotionTypeIds::LONG_SWIFTNESS => "swiftness",
				PotionTypeIds::STRONG_SWIFTNESS => "swiftness",
				PotionTypeIds::SLOWNESS => "slowness",
				PotionTypeIds::LONG_SLOWNESS => "slowness",
				PotionTypeIds::WATER_BREATHING => "water_breathing",
				PotionTypeIds::LONG_WATER_BREATHING => "water_breathing",
				PotionTypeIds::HEALING => "healing",
				PotionTypeIds::STRONG_HEALING => "healing",
				PotionTypeIds::HARMING => "harming",
				PotionTypeIds::STRONG_HARMING => "harming",
				PotionTypeIds::POISON => "potion",
				PotionTypeIds::LONG_POISON => "potion",
				PotionTypeIds::STRONG_POISON => "potion",
				PotionTypeIds::REGENERATION => "regeneration",
				PotionTypeIds::LONG_REGENERATION => "regeneration",
				PotionTypeIds::STRONG_REGENERATION => "regeneration",
				PotionTypeIds::STRENGTH => "strength",
				PotionTypeIds::LONG_STRENGTH => "strength",
				PotionTypeIds::STRONG_STRENGTH => "strength",
				PotionTypeIds::WEAKNESS => "weakness",
				PotionTypeIds::LONG_WEAKNESS => "weakness",
				PotionTypeIds::WITHER => "wither"
			];

			foreach($names as $potionKey => $pName){
				if(isset($foundItems["potion." . $pName])){
					if(isset($foundItems["item.potion.name"])){
						ItemFactory::getInstance()->register(new Potion(new ItemIdentifier(ItemIds::POTION, $potionKey), $foundItems["potion." . $pName] . " " . $foundItems["item.potion.name"], ), true);
					}
					if(isset($foundItems["potion.prefix.grenade"])){
						ItemFactory::getInstance()->register(new SplashPotion(new ItemIdentifier(ItemIds::SPLASH_POTION, $potionKey), $foundItems["potion." . $pName] . " " . $foundItems["potion.prefix.grenade"], $potionKey), true);
					}
				}
			}
			*/

			$air = ItemFactory::air();
			$key = "item." . $name($air) . ".name";
			if(isset($foundItems[$key])){
				$refP = new \ReflectionProperty($air, "name");
				$refP->setAccessible(true);
				$refP->setValue($air, $foundItems["item." . $name($air) . ".name"]);
			}
		}));
	}

	public function parse(string $input) : array{
		$res = str_replace("\t", "", $input);

		$result = [];

		foreach(explode("\n", ($res)) as $key => $str){
			if(str_contains($str, "##")){
				continue;
			}
			$s = explode("=", str_replace("#", "", rtrim($str)));
			if(isset($s[1])){
				$result[$s[0]] = $s[1];
			}
		}
		return $result;
	}

	private function findItem(array $input) : array{
		$output = [];
		foreach($input as $key => $value){
			if(str_starts_with($key, "item.") || str_starts_with($key, "tile.") || str_starts_with($key, "potion.")){
				$output[strtolower($key)] = $value;
			}
		}
		return $output;
	}

	public function replaceItem(array $data, Item $item) : void{
		$nameField = new \ReflectionProperty(Item::class, "name");
		$nameField->setAccessible(true);

		$key = $data["item.{$item->getName()}.name"];

		if(isset($data[$key])){
			$nameField->setValue($item, $data[$key]);
		}
		ItemFactory::getInstance()->register($item, true); // override
	}

	private function cleanItemName(string $str) : string{
		//"/[^A-Za-z0-9 ]/"
		return preg_replace("/[^A-Za-z0-9_ ]/", "", $str);
	}
}