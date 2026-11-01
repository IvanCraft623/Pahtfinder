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

use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\world\BlockGetter;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use function abs;
use function count;

abstract class PathfindingTestCase extends TestCase{
	protected const FLOOR_Y = 63;

	/**
	 * Stone floor at {@link FLOOR_Y} from 0,0 to $size-1,$size-1.
	 */
	protected static function flatWorld(int $size = 32) : TestWorld{
		return (new TestWorld())->fill(0, self::FLOOR_Y, 0, $size - 1, self::FLOOR_Y, $size - 1, VanillaBlocks::STONE());
	}

	/**
	 * A zombie sized mob.
	 */
	protected static function zombie(
		float $width = 0.6,
		float $height = 1.95,
		?BlockPathTypeCostMap $costMap = null,
		bool $canPassDoors = false,
		bool $canOpenDoors = false
	) : MovementProfile{
		return new MovementProfile(
			$width,
			$height,
			costMap: $costMap ?? new BlockPathTypeCostMap(),
			canPassDoors: $canPassDoors,
			canOpenDoors: $canOpenDoors
		);
	}

	/**
	 * Feet position of a mob centered on the block above the floor at x, z.
	 */
	protected static function standingAt(int $x, int $z, int $floorY = self::FLOOR_Y) : Vector3{
		return new Vector3($x + 0.5, $floorY + 1, $z + 0.5);
	}

	/**
	 * Runs the search the async task runs, on this thread.
	 */
	protected static function search(
		NodeEvaluator $evaluator,
		TestWorld|BlockGetter $world,
		Vector3 $start,
		Vector3 $target,
		int $maxVisitedNodes = 2000,
		float $maxDistanceFromStart = 64,
		int $reachRange = 0,
		?MovementProfile $profile = null
	) : Path{
		return PathFinder::search(
			$evaluator,
			$profile ?? self::zombie(),
			$world instanceof TestWorld ? $world->getBlockGetter() : $world,
			new SearchRequest($start, $target, $maxVisitedNodes, $maxDistanceFromStart, $reachRange)
		);
	}

	protected static function assertReached(Path $path, Vector3 $target) : void{
		self::assertSame(PathResult::REACHED, $path->getPathResult());
		$end = $path->getEndNode();
		self::assertNotNull($end);
		self::assertTrue($end->floor()->equals($target->floor()), "Path ends at $end, not at {$target->floor()}");
	}

	/**
	 * Every node is next to the previous one, at most one block away on each axis or straight down.
	 */
	protected static function assertContiguous(Path $path) : void{
		$nodes = $path->getNodes();
		for ($i = 1, $count = count($nodes); $i < $count; ++$i) {
			$previous = $nodes[$i - 1];
			$node = $nodes[$i];
			self::assertLessThanOrEqual(1, abs($node->x() - $previous->x()), "Jump on x from $previous to $node");
			self::assertLessThanOrEqual(1, abs($node->z() - $previous->z()), "Jump on z from $previous to $node");
			self::assertLessThanOrEqual(1, $node->y() - $previous->y(), "Climbed more than a block from $previous to $node");
		}
	}

	protected static function pathHas(Path $path, int $x, int $y, int $z) : bool{
		foreach ($path->getNodes() as $node) {
			if ($node->x() === $x && $node->y() === $y && $node->z() === $z) {
				return true;
			}
		}
		return false;
	}
}
