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

use PHPUnit\Framework\TestCase;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;

final class MovementProfileTest extends TestCase{
	public function testNodeSize() : void{
		$zombie = new MovementProfile(0.6, 1.95);
		self::assertSame(1, $zombie->nodeWidth);
		self::assertSame(2, $zombie->nodeHeight);

		$golem = new MovementProfile(1.4, 2.9);
		self::assertSame(2, $golem->nodeWidth);
		self::assertSame(3, $golem->nodeHeight);
	}

	public function testStandingOnFluids() : void{
		$strider = new MovementProfile(0.9, 1.7, liquidsThatCanStandOn: [BlockTypeIds::LAVA]);
		self::assertTrue($strider->canStandOnFluid(VanillaBlocks::LAVA()));
		self::assertFalse($strider->canStandOnFluid(VanillaBlocks::WATER()));
	}

	public function testCostMapIsASnapshot() : void{
		$costMap = new BlockPathTypeCostMap();
		$profile = new MovementProfile(0.6, 1.95, costMap: $costMap);
		$costMap->setPathfindingMalus(BlockPathType::WATER, -1.0);

		self::assertSame(BlockPathType::WATER->getMalus(), $profile->costMap->getPathfindingMalus(BlockPathType::WATER));
	}

	public function testStartBoundingBox() : void{
		$context = new SearchContext((new TestWorld())->getBlockGetter(), new MovementProfile(0.6, 1.95), new Vector3(10.5, 64, -3.5), true);
		$bb = $context->getStartBoundingBox();
		self::assertEqualsWithDelta([10.2, 64.0, -3.8, 10.8, 65.95, -3.2], [$bb->minX, $bb->minY, $bb->minZ, $bb->maxX, $bb->maxY, $bb->maxZ], 1e-9);
	}
}
