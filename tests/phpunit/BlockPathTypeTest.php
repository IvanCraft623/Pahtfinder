<?php

/*
 *  _____      _   _      __ _           _
 * |  __ \    | | | |    / _(_)         | |
 * | |__) |_ _| |_| |__ | |_ _ _ __   __| | ___ _ __
 * |  ___/ _` | __| '_ \|  _| | '_ \ / _` |/ _ \ '__|
 * | |  | (_| | |_| | | | | | | | | | (_| |  __/ |
 * |_|   \__,_|\__|_| |_|_| |_|_| |_|\__,_|\___|_|
 *
 * A PocketMine-MP virion that implements a mob-oriented pathfinding.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\Pathfinder;

use IvanCraft623\Pathfinder\pathtype\BlockClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;

final class BlockPathTypeTest extends TestCase{
	/**
	 * @phpstan-return \Generator<string, array{Block, BlockPathType}>
	 */
	public static function rawTypes() : \Generator{
		yield "air" => [VanillaBlocks::AIR(), BlockPathType::OPEN];
		yield "stone" => [VanillaBlocks::STONE(), BlockPathType::BLOCKED];
		yield "slab" => [VanillaBlocks::SMOOTH_STONE_SLAB(), BlockPathType::BLOCKED];
		yield "tall grass" => [VanillaBlocks::TALL_GRASS(), BlockPathType::OPEN];
		yield "trapdoor" => [VanillaBlocks::OAK_TRAPDOOR(), BlockPathType::TRAPDOOR];
		yield "lily pad" => [VanillaBlocks::LILY_PAD(), BlockPathType::TRAPDOOR];
		yield "cactus" => [VanillaBlocks::CACTUS(), BlockPathType::DAMAGE_OTHER];
		yield "sweet berry bush" => [VanillaBlocks::SWEET_BERRY_BUSH(), BlockPathType::DAMAGE_OTHER];
		yield "cocoa" => [VanillaBlocks::COCOA_POD(), BlockPathType::COCOA];
		yield "water" => [VanillaBlocks::WATER(), BlockPathType::WATER];
		yield "lava" => [VanillaBlocks::LAVA(), BlockPathType::LAVA];
		yield "fire" => [VanillaBlocks::FIRE(), BlockPathType::DAMAGE_FIRE];
		yield "magma" => [VanillaBlocks::MAGMA(), BlockPathType::DAMAGE_FIRE];
		yield "lit campfire" => [VanillaBlocks::CAMPFIRE()->setLit(true), BlockPathType::DAMAGE_FIRE];
		yield "unlit campfire" => [VanillaBlocks::CAMPFIRE()->setLit(false), BlockPathType::OPEN];
		yield "closed wooden door" => [VanillaBlocks::OAK_DOOR(), BlockPathType::DOOR_WOOD_CLOSED];
		yield "closed iron door" => [VanillaBlocks::IRON_DOOR(), BlockPathType::DOOR_IRON_CLOSED];
		yield "open door" => [VanillaBlocks::OAK_DOOR()->setOpen(true), BlockPathType::DOOR_OPEN];
		yield "rail" => [VanillaBlocks::RAIL(), BlockPathType::RAIL];
		yield "leaves" => [VanillaBlocks::OAK_LEAVES(), BlockPathType::LEAVES];
		yield "fence" => [VanillaBlocks::OAK_FENCE(), BlockPathType::FENCE];
		yield "wall" => [VanillaBlocks::COBBLESTONE_WALL(), BlockPathType::FENCE];
		yield "closed fence gate" => [VanillaBlocks::OAK_FENCE_GATE(), BlockPathType::FENCE];
		yield "open fence gate" => [VanillaBlocks::OAK_FENCE_GATE()->setOpen(true), BlockPathType::OPEN];
	}

	#[DataProvider("rawTypes")]
	public function testRawType(Block $block, BlockPathType $expected) : void{
		$world = (new TestWorld())->set(0, 64, 0, $block);
		self::assertSame($expected, BlockClassifier::getRawType($world->getBlockGetter(), 0, 64, 0));
	}

	public function testTopDoorHalfTakesTheOpenStateOfTheBottomHalf() : void{
		$world = (new TestWorld())
			->set(0, 64, 0, VanillaBlocks::OAK_DOOR()->setOpen(true))
			->set(0, 65, 0, VanillaBlocks::OAK_DOOR()->setTop(true));
		self::assertSame(BlockPathType::DOOR_OPEN, BlockClassifier::getRawType($world->getBlockGetter(), 0, 65, 0));
	}

	/**
	 * Type of the air block at 0,64,0 with $below under it.
	 *
	 * @phpstan-return \Generator<string, array{Block, BlockPathType}>
	 */
	public static function typesAbove() : \Generator{
		yield "stone" => [VanillaBlocks::STONE(), BlockPathType::WALKABLE];
		yield "air" => [VanillaBlocks::AIR(), BlockPathType::OPEN];
		yield "water" => [VanillaBlocks::WATER(), BlockPathType::OPEN];
		yield "lava" => [VanillaBlocks::LAVA(), BlockPathType::OPEN];
		yield "magma" => [VanillaBlocks::MAGMA(), BlockPathType::DAMAGE_FIRE];
		yield "cactus" => [VanillaBlocks::CACTUS(), BlockPathType::DAMAGE_OTHER];
		yield "fence" => [VanillaBlocks::OAK_FENCE(), BlockPathType::WALKABLE];
	}

	#[DataProvider("typesAbove")]
	public function testTypeAbove(Block $below, BlockPathType $expected) : void{
		$world = (new TestWorld())->set(0, 63, 0, $below);
		self::assertSame($expected, BlockClassifier::getType($world->getBlockGetter(), 0, 64, 0));
	}

	/**
	 * Type of the walkable block at 0,64,0 with $neighbour diagonally next to it.
	 *
	 * @phpstan-return \Generator<string, array{Block, BlockPathType}>
	 */
	public static function neighbourHazards() : \Generator{
		yield "cactus" => [VanillaBlocks::CACTUS(), BlockPathType::DANGER_OTHER];
		yield "sweet berry bush" => [VanillaBlocks::SWEET_BERRY_BUSH(), BlockPathType::DANGER_OTHER];
		yield "fire" => [VanillaBlocks::FIRE(), BlockPathType::DANGER_FIRE];
		yield "lava" => [VanillaBlocks::LAVA(), BlockPathType::DANGER_FIRE];
		yield "water" => [VanillaBlocks::WATER(), BlockPathType::WATER_BORDER];
		yield "stone" => [VanillaBlocks::STONE(), BlockPathType::WALKABLE];
	}

	#[DataProvider("neighbourHazards")]
	public function testNeighbourHazard(Block $neighbour, BlockPathType $expected) : void{
		$world = (new TestWorld())
			->fill(-1, 63, -1, 1, 63, 1, VanillaBlocks::STONE())
			->set(1, 64, 1, $neighbour);
		self::assertSame($expected, BlockClassifier::getType($world->getBlockGetter(), 0, 64, 0));
	}

	public function testUnsentChunksReadAsAir() : void{
		self::assertSame(BlockPathType::OPEN, BlockClassifier::getRawType((new TestWorld())->getBlockGetter(), 1000, 64, 1000));
	}
}
