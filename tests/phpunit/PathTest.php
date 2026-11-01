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
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function count;
use const INF;

final class PathTest extends TestCase{
	/**
	 * @param int[][] $positions
	 * @phpstan-param list<array{int, int, int}> $positions
	 */
	private static function path(array $positions, int $nodeWidth = 1, int $nodeHeight = 2) : Path{
		$nodes = [];
		foreach ($positions as [$x, $y, $z]) {
			$nodes[] = new Node($x, $y, $z);
		}
		$end = $positions[count($positions) - 1];
		return new Path($nodes, new Vector3($end[0], $end[1], $end[2]), PathResult::REACHED, $nodeWidth, $nodeHeight);
	}

	public function testWalking() : void{
		$path = self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0]]);
		self::assertTrue($path->notStarted());
		self::assertTrue($path->canReach());
		self::assertSame(0.0, $path->getDistanceToTarget());

		$path->advance();
		self::assertFalse($path->notStarted());
		self::assertSame(1, $path->getNextNode()->x());
		self::assertSame(0, $path->getPreviousNode()?->x());

		$path->advance();
		$path->advance();
		self::assertTrue($path->isDone());
	}

	public function testEmptyPath() : void{
		$path = new Path([], new Vector3(5, 64, 5), PathResult::CANCELLED);
		self::assertTrue($path->isDone());
		self::assertNull($path->getEndNode());
		self::assertSame(INF, $path->getDistanceToTarget());
		self::assertFalse($path->canReach());
	}

	public function testCorridorHoldsTheNodeTheFloorAndTheRing() : void{
		$path = self::path([[10, 64, 10]], 1, 2);

		self::assertTrue($path->isInCorridor(10, 64, 10));
		self::assertTrue($path->isInCorridor(10, 65, 10), "head");
		self::assertTrue($path->isInCorridor(10, 63, 10), "floor");
		self::assertTrue($path->isInCorridor(9, 64, 11), "ring");
		self::assertTrue($path->isInCorridor(11, 66, 11), "ring, above the head");
		self::assertFalse($path->isInCorridor(10, 67, 10));
		self::assertFalse($path->isInCorridor(10, 62, 10));
		self::assertFalse($path->isInCorridor(12, 64, 10));
	}

	public function testCorridorJoinsHeightsOfConsecutiveNodes() : void{
		$path = self::path([[0, 64, 0], [1, 60, 0]], 1, 2);
		//The column of the second node spans the fall from the first one
		self::assertTrue($path->isInCorridor(1, 64, 0));
		self::assertTrue($path->isInCorridor(1, 59, 0));
	}

	public function testCorridorForgetsWalkedNodes() : void{
		$path = self::path([[0, 64, 0], [5, 64, 0], [10, 64, 0]]);
		$path->advance();
		self::assertTrue($path->isInCorridor(0, 64, 0), "the node being left still counts");
		$path->advance();
		self::assertFalse($path->isInCorridor(0, 64, 0));
		self::assertTrue($path->isInCorridor(5, 64, 0));
	}

	public function testCorridorFollowsNodeChanges() : void{
		$path = self::path([[0, 64, 0], [5, 64, 0], [10, 64, 0]]);
		$path->truncateNodes(2);
		self::assertFalse($path->isInCorridor(10, 64, 0));

		$path->replaceNode(1, new Node(5, 70, 0));
		self::assertTrue($path->isInCorridor(5, 70, 0));
		self::assertTrue($path->isInCorridor(5, 63, 0), "joined to the previous node's height");
		self::assertFalse($path->isInCorridor(5, 62, 0));
	}

	public function testCorridorChunksIncludeTheRingAcrossChunkBorders() : void{
		$path = self::path([[15, 64, 0]]);
		$chunks = $path->getCorridorChunks();

		self::assertArrayHasKey(World::chunkHash(0, 0), $chunks);
		self::assertArrayHasKey(World::chunkHash(1, 0), $chunks);
		self::assertArrayHasKey(World::chunkHash(0, -1), $chunks);
		self::assertArrayHasKey(World::chunkHash(1, -1), $chunks);
		self::assertCount(4, $chunks);
	}

	public function testNodeCenter() : void{
		$path = self::path([[3, 64, -5], [4, 64, -5]], 1);
		self::assertTrue($path->getNodeCenter(0)->equals(new Vector3(3.5, 64, -4.5)));
		self::assertTrue($path->getNextNodeCenter()->equals(new Vector3(3.5, 64, -4.5)));
		$path->advance();
		self::assertTrue($path->getNextNodeCenter()->equals(new Vector3(4.5, 64, -4.5)));

		$wide = self::path([[3, 64, -5]], 2);
		self::assertTrue($wide->getNodeCenter(0)->equals(new Vector3(4, 64, -4)), "a 2x2 node's center is its corner block's far corner");
	}

	public function testEquals() : void{
		$a = self::path([[0, 64, 0], [1, 64, 0]]);
		self::assertTrue($a->equals(self::path([[0, 64, 0], [1, 64, 0]])));
		self::assertFalse($a->equals(self::path([[0, 64, 0], [1, 65, 0]])));
		self::assertFalse($a->equals(self::path([[0, 64, 0]])));
	}
}
