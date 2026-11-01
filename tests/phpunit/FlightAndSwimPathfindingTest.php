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

use IvanCraft623\Pathfinder\evaluator\AmphibiousNodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\FlightNodeEvaluator;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;

final class FlightAndSwimPathfindingTest extends PathfindingTestCase{
	public function testFliesOverAWall() : void{
		//Wall across the whole sent area, too high to step over
		$world = self::flatWorld()->fill(10, 64, 0, 10, 70, 31, VanillaBlocks::STONE());
		$start = self::standingAt(5, 15);
		$target = self::standingAt(15, 15);
		$evaluator = new FlightNodeEvaluator();
		self::setUpMob($evaluator, $start);
		$path = self::search($evaluator, $world, $start, $target);

		self::assertReached($path, $target);
		self::assertContiguous($path);
		foreach ($path->getNodes() as $node) {
			if ($node->x() === 10) {
				self::assertGreaterThan(70, $node->y(), "goes over the wall, not through it");
			}
		}
	}

	/**
	 * Stone floor with a pool, water from y=58 to the floor level, x 10..20, z 1..10.
	 */
	private static function poolWorld() : TestWorld{
		return self::flatWorld()
			->fill(9, 57, 0, 21, self::FLOOR_Y, 11, VanillaBlocks::STONE())
			->fill(10, 58, 1, 20, self::FLOOR_Y, 10, VanillaBlocks::WATER());
	}

	public function testAmphibiousMobSwimsDown() : void{
		$start = self::standingAt(5, 5);
		$target = new Vector3(15.5, 59, 5.5);
		$evaluator = new AmphibiousNodeEvaluator();
		self::setUpMob($evaluator, $start);
		$path = self::search($evaluator, self::poolWorld(), $start, $target);

		self::assertReached($path, $target);
		self::assertContiguous($path);
		self::assertSame(BlockPathType::WATER, $path->getEndNode()?->type);
	}

	/**
	 * Water touching the pool's floor is WATER_BORDER, and only WATER is entered vertically, as in vanilla.
	 */
	public function testAmphibiousMobReachesThePoolFloorFromAbove() : void{
		$start = self::standingAt(5, 5);
		$target = new Vector3(15.5, 58, 5.5);
		$evaluator = new AmphibiousNodeEvaluator();
		self::setUpMob($evaluator, $start);

		$exact = self::search($evaluator, self::poolWorld(), $start, $target);
		self::assertSame(PathResult::BLOCKED, $exact->getPathResult());
		self::assertSame(59, $exact->getEndNode()?->y());

		$withinReach = self::search($evaluator, self::poolWorld(), $start, $target, reachRange: 1);
		self::assertSame(PathResult::REACHED, $withinReach->getPathResult());
	}

	public function testAmphibiousMobWalksOutOfWater() : void{
		$start = new Vector3(15.5, 58, 5.5);
		$target = self::standingAt(25, 5);
		$evaluator = new AmphibiousNodeEvaluator();
		self::setUpMob($evaluator, $start);
		$path = self::search($evaluator, self::poolWorld(), $start, $target);

		self::assertReached($path, $target);
		self::assertContiguous($path);
	}
}
