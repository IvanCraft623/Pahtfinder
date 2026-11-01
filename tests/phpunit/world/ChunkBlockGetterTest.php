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

namespace IvanCraft623\Pathfinder\world;

use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\pathtype\BlockClassifier;
use IvanCraft623\Pathfinder\TestWorld;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;

final class ChunkBlockGetterTest extends TestCase{
	public function testMayContainAnyLooksAtTheSectionsInTheBox() : void{
		$world = (new TestWorld())->set(17, 70, 3, VanillaBlocks::CACTUS());
		$getter = $world->getBlockGetter();
		$cactus = new BlockStateSet([VanillaBlocks::CACTUS()->getStateId()]);

		self::assertTrue($getter->mayContainAny($cactus, 16, 64, 0, 16, 64, 0), "same section, not the same block");
		self::assertTrue($getter->mayContainAny($cactus, 15, 63, 0, 16, 64, 0), "box across the chunk and section borders");
		self::assertFalse($getter->mayContainAny($cactus, 0, 64, 0, 15, 79, 15), "neighbour chunk");
		self::assertFalse($getter->mayContainAny($cactus, 16, 80, 0, 31, 90, 15), "section above");
		self::assertFalse($getter->mayContainAny($cactus, 16, 48, 0, 31, 63, 15), "section below");
	}

	public function testUnsentAndEmptyPartsHoldAir() : void{
		$getter = (new TestWorld())->set(0, 64, 0, VanillaBlocks::STONE())->getBlockGetter();
		$air = new BlockStateSet([VanillaBlocks::AIR()->getStateId()]);
		$stone = new BlockStateSet([VanillaBlocks::STONE()->getStateId()]);

		self::assertTrue($getter->mayContainAny($air, 100, 64, 100, 100, 64, 100), "unsent chunk");
		self::assertFalse($getter->mayContainAny($stone, 100, 64, 100, 100, 64, 100));
		self::assertTrue($getter->mayContainAny($air, 0, 100, 0, 0, 100, 0), "empty section");
		self::assertFalse($getter->mayContainAny($stone, 0, 100, 0, 0, 100, 0));
	}

	public function testNothingOutsideTheWorldHeight() : void{
		$getter = (new TestWorld())->fill(0, 0, 0, 0, 1, 0, VanillaBlocks::STONE())->getBlockGetter(0, 128);
		$air = new BlockStateSet([VanillaBlocks::AIR()->getStateId()]);

		self::assertFalse($getter->mayContainAny($air, 0, 128, 0, 0, 200, 0));
		self::assertFalse($getter->mayContainAny($air, 0, -20, 0, 0, -1, 0));
	}

	public function testDangersAcrossChunkAndSectionBorders() : void{
		$world = (new TestWorld())
			->fill(-1, 63, -1, 1, 63, 1, VanillaBlocks::STONE())
			->set(-1, 64, 0, VanillaBlocks::CACTUS());
		self::assertSame(BlockPathType::DANGER_OTHER, BlockClassifier::getType($world->getBlockGetter(), 0, 64, 0), "in the chunk at x -1");

		$world = (new TestWorld())
			->fill(0, 63, 0, 2, 63, 2, VanillaBlocks::STONE())
			->set(2, 63, 2, VanillaBlocks::MAGMA());
		self::assertSame(BlockPathType::DANGER_FIRE, BlockClassifier::getType($world->getBlockGetter(), 1, 64, 1), "in the section below");
	}
}
