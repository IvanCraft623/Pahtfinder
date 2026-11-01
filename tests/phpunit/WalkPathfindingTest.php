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

use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;

final class WalkPathfindingTest extends PathfindingTestCase{
	private static function walker(Vector3 $start, ?BlockPathTypeCostMap $costMap = null) : WalkNodeEvaluator{
		$evaluator = new WalkNodeEvaluator($costMap);
		self::setUpMob($evaluator, $start);
		return $evaluator;
	}

	public function testDiagonal() : void{
		$start = self::standingAt(2, 2);
		$target = self::standingAt(12, 12);
		$path = self::search(self::walker($start), self::flatWorld(), $start, $target);

		self::assertReached($path, $target);
		self::assertContiguous($path);
		self::assertSame(11, $path->getNodeCount(), "diagonal moves");
	}

	public function testReachRangeStopsShort() : void{
		$start = self::standingAt(2, 2);
		$target = self::standingAt(20, 2);
		$path = self::search(self::walker($start), self::flatWorld(), $start, $target, reachRange: 2);

		self::assertSame(PathResult::REACHED, $path->getPathResult());
		self::assertSame(18, $path->getEndNode()?->x());
	}

	public function testClimbsOneBlockStep() : void{
		$world = self::flatWorld()->fill(10, 64, 0, 31, 64, 31, VanillaBlocks::STONE());
		$start = self::standingAt(2, 5);
		$target = self::standingAt(20, 5, 64);
		$path = self::search(self::walker($start), $world, $start, $target);

		self::assertReached($path, $target);
		self::assertContiguous($path);
		self::assertTrue(self::pathHas($path, 10, 65, 5), "steps up onto the ledge");
	}

	public function testDoesNotClimbTwoBlocks() : void{
		$world = self::flatWorld()->fill(10, 64, 0, 31, 65, 31, VanillaBlocks::STONE());
		$start = self::standingAt(2, 5);
		$target = self::standingAt(20, 5, 65);
		$path = self::search(self::walker($start), $world, $start, $target);

		self::assertSame(PathResult::BLOCKED, $path->getPathResult());
		self::assertSame(9, $path->getEndNode()?->x(), "ends at the foot of the cliff");
	}

	public function testGoesAroundAWall() : void{
		$world = self::flatWorld()->fill(10, 64, 0, 10, 65, 20, VanillaBlocks::STONE());
		$start = self::standingAt(5, 5);
		$target = self::standingAt(15, 5);
		$path = self::search(self::walker($start), $world, $start, $target);

		self::assertReached($path, $target);
		self::assertContiguous($path);
		self::assertTrue(self::pathHas($path, 10, 64, 21), "around the end of the wall");
	}

	public function testDropsUpToMaxFallDistance() : void{
		//Upper floor at y=66, ground at y=63 beyond x=10
		$world = self::flatWorld()->fill(0, 64, 0, 9, 66, 31, VanillaBlocks::STONE());
		$start = self::standingAt(2, 5, 66);
		$target = self::standingAt(20, 5);
		$path = self::search(self::walker($start), $world, $start, $target);

		self::assertReached($path, $target);
		self::assertTrue(self::pathHas($path, 10, 64, 5), "lands right below the edge");
	}

	public function testDoesNotDropFurtherThanMaxFallDistance() : void{
		$world = self::flatWorld()->fill(0, 64, 0, 9, 67, 31, VanillaBlocks::STONE());
		$start = self::standingAt(2, 5, 67);
		$target = self::standingAt(20, 5);
		$path = self::search(self::walker($start), $world, $start, $target);

		self::assertSame(PathResult::BLOCKED, $path->getPathResult());
	}

	public function testExhaustsTheNodeBudget() : void{
		$start = self::standingAt(2, 2);
		$target = self::standingAt(28, 28);
		$path = self::search(self::walker($start), self::flatWorld(), $start, $target, maxVisitedNodes: 5);

		self::assertSame(PathResult::EXHAUSTED, $path->getPathResult());
		self::assertFalse($path->canReach());
		self::assertContiguous($path);
	}

	public function testStopsAtMaxDistanceFromStart() : void{
		$start = self::standingAt(2, 2);
		$target = self::standingAt(30, 2);
		$path = self::search(self::walker($start), self::flatWorld(), $start, $target, maxDistanceFromStart: 10);

		self::assertSame(PathResult::BLOCKED, $path->getPathResult());
		self::assertLessThanOrEqual(12, $path->getEndNode()?->x());
	}

	public function testUnreachableTargetEndsAtTheClosestNode() : void{
		//Target inside a sealed 3x3 room
		$world = self::flatWorld()
			->fill(19, 64, 4, 23, 66, 8, VanillaBlocks::STONE())
			->fill(20, 64, 5, 22, 65, 7, VanillaBlocks::AIR());
		$start = self::standingAt(5, 6);
		$target = self::standingAt(21, 6);
		$path = self::search(self::walker($start), $world, $start, $target);

		self::assertSame(PathResult::BLOCKED, $path->getPathResult());
		self::assertSame(18, $path->getEndNode()?->x(), "against the room's wall");
	}

	/**
	 * Wall across the world with a door at x=10, z=5.
	 */
	private static function doorWorld(bool $iron = false) : TestWorld{
		$door = $iron ? VanillaBlocks::IRON_DOOR() : VanillaBlocks::OAK_DOOR();
		return self::flatWorld()
			->fill(10, 64, 0, 10, 66, 31, VanillaBlocks::STONE())
			->set(10, 64, 5, (clone $door)->setTop(false))
			->set(10, 65, 5, (clone $door)->setTop(true));
	}

	public function testOpensWoodenDoors() : void{
		$start = self::standingAt(5, 5);
		$target = self::standingAt(15, 5);
		$evaluator = self::walker($start);
		$evaluator->setCanOpenDoors();
		$evaluator->setCanPassDoors();
		$path = self::search($evaluator, self::doorWorld(), $start, $target);

		self::assertReached($path, $target);
		self::assertTrue(self::pathHas($path, 10, 64, 5));
		self::assertSame(BlockPathType::WALKABLE_DOOR, $path->getNode(5)->type);
	}

	public function testClosedDoorBlocksMobsThatCannotOpenIt() : void{
		$start = self::standingAt(5, 5);
		$target = self::standingAt(15, 5);
		$evaluator = self::walker($start);
		$evaluator->setCanPassDoors();
		$path = self::search($evaluator, self::doorWorld(), $start, $target);

		self::assertSame(PathResult::BLOCKED, $path->getPathResult());
	}

	public function testIronDoorsStayClosed() : void{
		$start = self::standingAt(5, 5);
		$target = self::standingAt(15, 5);
		$evaluator = self::walker($start);
		$evaluator->setCanOpenDoors();
		$evaluator->setCanPassDoors();
		$path = self::search($evaluator, self::doorWorld(true), $start, $target);

		self::assertSame(PathResult::BLOCKED, $path->getPathResult());
	}

	public function testDoesNotJumpFences() : void{
		$world = self::flatWorld()->fill(10, 64, 0, 10, 64, 31, VanillaBlocks::OAK_FENCE());
		$start = self::standingAt(5, 5);
		$target = self::standingAt(15, 5);
		$path = self::search(self::walker($start), $world, $start, $target);

		self::assertSame(PathResult::BLOCKED, $path->getPathResult());
	}

	public function testCostMapSteersAroundWater() : void{
		//One deep water strip across the way, with a dry gap at z=20
		$world = self::flatWorld()
			->fill(10, 62, 0, 12, 62, 31, VanillaBlocks::STONE())
			->fill(10, 63, 0, 12, 63, 31, VanillaBlocks::WATER())
			->fill(10, 63, 20, 12, 63, 20, VanillaBlocks::STONE());
		$start = self::standingAt(5, 5);
		$target = self::standingAt(18, 5);

		$path = self::search(self::walker($start), $world, $start, $target);
		self::assertReached($path, $target);

		$costMap = new BlockPathTypeCostMap();
		$costMap->setPathfindingMalus(BlockPathType::WATER, -1);
		$dryPath = self::search(self::walker($start, $costMap), $world, $start, $target);
		self::assertReached($dryPath, $target);
		self::assertTrue(self::pathHas($dryPath, 11, 64, 20), "crosses at the dry gap");
	}

	public function testWideMobNeedsWideGaps() : void{
		//Wall with a one block wide gap at z=5
		$world = self::flatWorld()
			->fill(10, 64, 0, 10, 66, 31, VanillaBlocks::STONE())
			->fill(10, 64, 5, 10, 66, 5, VanillaBlocks::AIR());
		$start = self::standingAt(5, 5);
		$target = self::standingAt(15, 5);

		$narrow = self::search(self::walker($start), $world, $start, $target);
		self::assertReached($narrow, $target);

		$evaluator = new WalkNodeEvaluator();
		self::setUpMob($evaluator, $start, width: 1.4, height: 1.4);
		$wide = self::search($evaluator, $world, $start, $target);
		self::assertSame(PathResult::BLOCKED, $wide->getPathResult());
	}
}
