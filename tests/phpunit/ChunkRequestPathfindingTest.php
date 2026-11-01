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
use IvanCraft623\Pathfinder\world\ArrayChunkSource;
use IvanCraft623\Pathfinder\world\ChunkBlockGetter;
use IvanCraft623\Pathfinder\world\CountingChunkRequester;
use IvanCraft623\Pathfinder\world\RequestingChunkSource;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

final class ChunkRequestPathfindingTest extends PathfindingTestCase{
	public function testSearchRequestsTheChunksItWasNotSent() : void{
		//Wall forcing a detour through chunks outside the start..target rectangle
		$world = self::flatWorld(64)->fill(20, 64, 0, 20, 66, 20, VanillaBlocks::STONE());
		$start = self::standingAt(10, 8);
		$target = self::standingAt(30, 8);

		$evaluator = new WalkNodeEvaluator();
		$allSent = self::search($evaluator, $world, $start, $target);
		self::assertReached($allSent, $target);

		$chunks = $world->getChunks();
		$requester = new CountingChunkRequester($chunks);
		$startChunk = World::chunkHash(0, 0);
		$getter = new ChunkBlockGetter(
			new RequestingChunkSource(new ArrayChunkSource([$startChunk => $chunks[$startChunk]]), $requester),
			World::Y_MIN,
			World::Y_MAX
		);
		$requested = self::search($evaluator, $getter, $start, $target);

		self::assertTrue($requested->equals($allSent), "same path as with every chunk sent");
		self::assertArrayHasKey(World::chunkHash(1, 1), $requester->requests, "the detour's chunk");
		self::assertArrayNotHasKey($startChunk, $requester->requests);
		foreach ($requester->requests as $times) {
			self::assertSame(1, $times);
		}
	}
}
