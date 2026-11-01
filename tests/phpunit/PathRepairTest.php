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

/**
 * Patching a path around a block that changed instead of searching from the mob again.
 */
final class PathRepairTest extends PathfindingTestCase{
	private const Y = self::FLOOR_Y + 1;

	/**
	 * @return array{Path, int} the path and how many nodes its search expanded
	 */
	private static function countedSearch(TestWorld $world, Vector3 $start, Vector3 $target, int $reachRange = 0, bool $startAtNode = false) : array{
		$expanded = 0;
		$path = (new AStar(new WalkNodeEvaluator(), self::zombie(), $world->getBlockGetter()))->search(
			new SearchRequest($start, $target, 2000, 64, $reachRange, startAtNode: $startAtNode),
			function() use (&$expanded) : bool{
				++$expanded;
				return false;
			}
		);
		return [$path, $expanded];
	}

	/**
	 * Changes the world, then repairs the path as a navigation would.
	 *
	 * @return array{Path, PathRepair, int} the repaired path, the repair and how many nodes the repair expanded
	 */
	private static function repair(Path $path, TestWorld $changed, int $x, int $y, int $z, ?PathDamage $known = null) : array{
		$damage = $path->findDamage($x, $y, $z);
		self::assertNotNull($damage, "the change is on the path");
		$repair = $path->planRepair($known?->union($damage) ?? $damage);
		self::assertNotNull($repair);

		$target = $repair->rejoin === null ? $path->getTarget() : $path->getNode($repair->rejoin);
		[$bridge, $expanded] = self::countedSearch($changed, $path->getNode($repair->from), $target, 0, true);
		self::assertSame(PathResult::REACHED, $bridge->getPathResult());
		return [$path->splice($repair->from, $bridge, $repair->rejoin), $repair, $expanded];
	}

	public function testDetourAroundABlockedStretch() : void{
		$world = self::flatWorld(64);
		$start = self::standingAt(2, 5);
		$target = self::standingAt(60, 5);
		[$path] = self::countedSearch($world, $start, $target);
		self::assertReached($path, $target);
		for ($i = 0; $i < 3; ++$i) {
			$path->advance();
		}

		$changed = $world->fill(15, self::Y, 3, 15, self::Y + 1, 7, VanillaBlocks::STONE());
		[$repaired, $repair, $expanded] = self::repair($path, $changed, 15, self::Y, 5);

		self::assertNotNull($repair->rejoin, "rejoins the old path");
		self::assertReached($repaired, $target);
		self::assertContiguous($repaired);
		self::assertSame(3, $repaired->getNextNodeIndex());
		self::assertFalse(self::pathHas($repaired, 15, self::Y, 5));
		self::assertTrue($repaired->getNode($repaired->getNodeCount() - 2)->equals($path->getNode($path->getNodeCount() - 2)), "keeps the tail");

		[, $fullExpanded] = self::countedSearch($changed, self::standingAt(5, 5), $target);
		self::assertLessThan($fullExpanded / 2, $expanded, "less than a new path from the mob");
	}

	public function testDetourAroundAHole() : void{
		$world = self::flatWorld();
		[$path] = self::countedSearch($world, self::standingAt(2, 5), self::standingAt(28, 5));

		$changed = $world->fill(14, self::FLOOR_Y, 5, 16, self::FLOOR_Y, 5, VanillaBlocks::AIR());
		[$repaired] = self::repair($path, $changed, 15, self::FLOOR_Y, 5);

		self::assertReached($repaired, self::standingAt(28, 5));
		self::assertContiguous($repaired);
		foreach ($repaired->getNodes() as $node) {
			self::assertFalse($node->x() >= 14 && $node->x() <= 16 && $node->z() === 5 && $node->y() === self::Y, "walks over the hole at $node");
		}
	}

	public function testDamageInTheEndRepairsToTheTarget() : void{
		$world = self::flatWorld();
		$target = self::standingAt(20, 5);
		[$path] = self::countedSearch($world, self::standingAt(2, 5), $target);

		$changed = $world->fill(19, self::Y, 3, 19, self::Y + 1, 7, VanillaBlocks::STONE());
		[$repaired, $repair] = self::repair($path, $changed, 19, self::Y, 5);

		self::assertNull($repair->rejoin);
		self::assertReached($repaired, $target);
		self::assertFalse(self::pathHas($repaired, 19, self::Y, 5));
	}

	public function testSeveralChangesAreOneRepair() : void{
		$world = self::flatWorld();
		$target = self::standingAt(28, 5);
		[$path] = self::countedSearch($world, self::standingAt(2, 5), $target);

		$first = $path->findDamage(10, self::Y, 5);
		$changed = $world->fill(10, self::Y, 3, 10, self::Y + 1, 7, VanillaBlocks::STONE())->fill(20, self::Y, 3, 20, self::Y + 1, 7, VanillaBlocks::STONE());
		[$repaired, $repair] = self::repair($path, $changed, 20, self::Y, 5, $first);

		self::assertSame(8, $path->getNode($repair->from)->x(), "from before the first change");
		self::assertReached($repaired, $target);
		self::assertFalse(self::pathHas($repaired, 10, self::Y, 5));
		self::assertFalse(self::pathHas($repaired, 20, self::Y, 5));
	}
}
