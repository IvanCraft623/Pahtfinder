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

namespace IvanCraft623\Pathfinder\execution;

use IvanCraft623\Pathfinder\AStar;
use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathfindingTestCase;
use IvanCraft623\Pathfinder\PathResult;
use IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\Pathfinder\TestWorld;
use IvanCraft623\Pathfinder\world\ArrayChunkSource;
use IvanCraft623\Pathfinder\world\Terrain;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class SearchExecutionTest extends PathfindingTestCase{
	private static function terrain(TestWorld $world) : Terrain{
		return new Terrain(new ArrayChunkSource($world->getChunks()), World::Y_MIN, World::Y_MAX);
	}

	public function testSyncExecutorResolvesOnReturn() : void{
		$start = self::standingAt(2, 2);
		$target = self::standingAt(20, 2);
		$evaluator = new WalkNodeEvaluator();

		$search = (new SyncSearchExecutor())->search($evaluator, self::zombie(), self::terrain(self::flatWorld()), new SearchRequest($start, $target, 2000, 64, 0));
		$resolved = null;
		$search->getPromise()->onCompletion(function(Path $path) use (&$resolved) : void{
			$resolved = $path;
		}, static function() : void{
			self::fail("rejected");
		});
		$search->cancel(); //too late, no effect

		self::assertInstanceOf(Path::class, $resolved);
		self::assertReached($resolved, $target);
	}

	public function testRequestDropsTheWorldOfPositions() : void{
		$request = new SearchRequest(new Position(1, 2, 3, null), new Position(4, 5, 6, null), 10, 10);
		self::assertSame(Vector3::class, $request->start::class);
		self::assertSame(Vector3::class, $request->target::class);
	}

	public function testCancellingStopsARunningSearch() : void{
		$start = self::standingAt(2, 2);
		$evaluator = new WalkNodeEvaluator();

		$checks = 0;
		$path = (new AStar($evaluator, self::zombie(), self::flatWorld()->getBlockGetter()))->search(
			new SearchRequest($start, self::standingAt(28, 28), 2000, 64, 0),
			function() use (&$checks) : bool{
				return ++$checks > 5;
			}
		);
		self::assertSame(PathResult::CANCELLED, $path->getPathResult());
		self::assertSame(6, $checks);
	}

	public function testTargetAtNegativeCoordinates() : void{
		$world = (new TestWorld())->fill(-20, self::FLOOR_Y, -20, -1, self::FLOOR_Y, -1, VanillaBlocks::STONE());
		$start = new Vector3(-2.5, self::FLOOR_Y + 1, -2.5);
		$target = new Vector3(-15.5, self::FLOOR_Y + 1, -2.5);
		$evaluator = new WalkNodeEvaluator();

		$path = self::search($evaluator, $world, $start, $target);
		self::assertReached($path, $target);
		self::assertSame(-16, $path->getEndNode()?->x());
	}
}
