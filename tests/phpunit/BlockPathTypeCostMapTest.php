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

final class BlockPathTypeCostMapTest extends TestCase{
	public function testUnsetTypesUseTheirDefault() : void{
		$costMap = new BlockPathTypeCostMap();
		self::assertSame(BlockPathType::WATER->getMalus(), $costMap->getPathfindingMalus(BlockPathType::WATER));

		$costMap->setPathfindingMalus(BlockPathType::WATER, -1.0);
		self::assertSame(-1.0, $costMap->getPathfindingMalus(BlockPathType::WATER));
	}

	/**
	 * A mob's own costs win over a preset, and neither map changes.
	 */
	public function testMergeOverwritesWithTheGivenCosts() : void{
		$mob = new BlockPathTypeCostMap();
		$mob->setPathfindingMalus(BlockPathType::WATER, 3.0);
		$preset = BlockPathTypeCostMap::amphibious();
		$merged = $preset->merge($mob);

		self::assertSame(3.0, $merged->getPathfindingMalus(BlockPathType::WATER));
		self::assertSame(6.0, $merged->getPathfindingMalus(BlockPathType::WALKABLE));
		self::assertSame(4.0, $merged->getPathfindingMalus(BlockPathType::WATER_BORDER));
		self::assertSame(BlockPathType::LAVA->getMalus(), $merged->getPathfindingMalus(BlockPathType::LAVA));

		self::assertSame(BlockPathType::WALKABLE->getMalus(), $mob->getPathfindingMalus(BlockPathType::WALKABLE));
		self::assertSame(0.0, $preset->getPathfindingMalus(BlockPathType::WATER));
	}

	public function testMergedMapIsACopy() : void{
		$mob = new BlockPathTypeCostMap();
		$merged = BlockPathTypeCostMap::amphibious()->merge($mob);
		$mob->setPathfindingMalus(BlockPathType::WALKABLE, 1.0);

		self::assertSame(6.0, $merged->getPathfindingMalus(BlockPathType::WALKABLE));
	}
}
