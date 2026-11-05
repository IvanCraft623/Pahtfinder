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
use function array_slice;
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
		$cursor = new PathCursor(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0]]));
		self::assertTrue($cursor->notStarted());
		self::assertTrue($cursor->getPath()->canReach());
		self::assertSame(0.0, $cursor->getPath()->getDistanceToTarget());

		$cursor->advance();
		self::assertFalse($cursor->notStarted());
		self::assertSame(1, $cursor->getNextNode()->x());
		self::assertSame(0, $cursor->getPreviousNode()?->x());

		$cursor->advance();
		$cursor->advance();
		self::assertTrue($cursor->isDone());
	}

	public function testEmptyPath() : void{
		$path = new Path([], new Vector3(5, 64, 5), PathResult::CANCELLED);
		self::assertTrue((new PathCursor($path))->isDone());
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
		$cursor = new PathCursor(self::path([[0, 64, 0], [5, 64, 0], [10, 64, 0]]));
		$cursor->advance();
		self::assertTrue($cursor->isInCorridor(0, 64, 0), "the node being left still counts");
		$cursor->advance();
		self::assertFalse($cursor->isInCorridor(0, 64, 0));
		self::assertTrue($cursor->isInCorridor(5, 64, 0));
	}

	public function testCorridorFollowsNodeChanges() : void{
		$path = self::path([[0, 64, 0], [5, 64, 0], [10, 64, 0]]);
		$truncated = $path->withNodes(array_slice($path->getNodes(), 0, 2));
		self::assertFalse($truncated->isInCorridor(10, 64, 0));
		self::assertTrue($path->isInCorridor(10, 64, 0), "leaves the path alone");

		$moved = $truncated->withNodes([$truncated->getNode(0), new Node(5, 70, 0)]);
		self::assertTrue($moved->isInCorridor(5, 70, 0));
		self::assertTrue($moved->isInCorridor(5, 63, 0), "joined to the previous node's height");
		self::assertFalse($moved->isInCorridor(5, 62, 0));
		self::assertTrue($moved->getTarget()->equals($path->getTarget()), "keeps the target");
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
		$cursor = new PathCursor(self::path([[3, 64, -5], [4, 64, -5]], 1));
		self::assertTrue($cursor->getPath()->getNodeCenter(0)->equals(new Vector3(3.5, 64, -4.5)));
		self::assertTrue($cursor->getNextNodeCenter()->equals(new Vector3(3.5, 64, -4.5)));
		$cursor->advance();
		self::assertTrue($cursor->getNextNodeCenter()->equals(new Vector3(4.5, 64, -4.5)));

		$wide = self::path([[3, 64, -5]], 2);
		self::assertTrue($wide->getNodeCenter(0)->equals(new Vector3(4, 64, -4)), "a 2x2 node's center is its corner block's far corner");
	}

	public function testClosestNodeIsAhead() : void{
		$cursor = new PathCursor(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0]]));
		self::assertSame(2, $cursor->getClosestNodeIndex(new Vector3(2, 64, 3)));

		$cursor->advance();
		$cursor->advance();
		$cursor->advance();
		self::assertSame(3, $cursor->getClosestNodeIndex(new Vector3(0, 64, 0)), "walked nodes don't count");

		$cursor->advance();
		$this->expectException(\LogicException::class);
		$cursor->getClosestNodeIndex(new Vector3(0, 64, 0));
	}

	public function testSplice() : void{
		$path = self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0]]);
		$cursor = new PathCursor($path, 1);
		$continuation = new Path([new Node(2, 64, 0), new Node(2, 64, 1), new Node(2, 64, 2)], new Vector3(2, 64, 2), PathResult::REACHED);

		$spliced = $cursor->splice(2, $continuation);
		self::assertNotSame($cursor, $spliced);
		self::assertTrue($spliced->getPath()->equals(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [2, 64, 1], [2, 64, 2]])));
		self::assertSame(1, $spliced->getNextNodeIndex(), "keeps the progress");
		self::assertTrue($spliced->getPath()->getTarget()->equals(new Vector3(2, 64, 2)));
		self::assertSame(PathResult::REACHED, $spliced->getPath()->getPathResult());
		self::assertSame(4, $path->getNodeCount(), "leaves the spliced path alone");
	}

	public function testSpliceTakesTheContinuationResult() : void{
		$path = self::path([[0, 64, 0], [1, 64, 0]]);
		$continuation = new Path([new Node(1, 64, 0), new Node(2, 64, 0)], new Vector3(9, 64, 0), PathResult::BLOCKED);
		$spliced = $path->splice(1, $continuation);

		self::assertSame(PathResult::BLOCKED, $spliced->getPathResult());
		self::assertSame(7.0, $spliced->getDistanceToTarget());
	}

	public function testSpliceNeedsTheContinuationToStartAtTheNode() : void{
		$path = self::path([[0, 64, 0], [1, 64, 0]]);
		$this->expectException(\InvalidArgumentException::class);
		$path->splice(1, new Path([new Node(5, 64, 0)], new Vector3(5, 64, 0), PathResult::REACHED));
	}

	public function testSpliceCantDropWalkedNodes() : void{
		$cursor = new PathCursor(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0]]), 3);
		$cursor->splice(2, new Path([new Node(2, 64, 0)], new Vector3(2, 64, 0), PathResult::REACHED)); //the node being left is fine

		$this->expectException(\InvalidArgumentException::class);
		$cursor->splice(1, new Path([new Node(1, 64, 0)], new Vector3(1, 64, 0), PathResult::REACHED));
	}

	public function testSpliceRejoinsTheRestOfThePath() : void{
		$cursor = new PathCursor(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0], [4, 64, 0], [5, 64, 0]]), 1);
		$detour = new Path([new Node(1, 64, 0), new Node(2, 64, 1), new Node(3, 64, 1), new Node(4, 64, 0)], new Vector3(4, 64, 0), PathResult::REACHED);

		$spliced = $cursor->splice(1, $detour, 4);
		self::assertTrue($spliced->getPath()->equals(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 1], [3, 64, 1], [4, 64, 0], [5, 64, 0]])));
		self::assertSame(1, $spliced->getNextNodeIndex());
		self::assertTrue($spliced->getPath()->getTarget()->equals(new Vector3(5, 64, 0)), "keeps the target");
	}

	public function testSpliceRejoinCanShortcutTheNodesBetween() : void{
		$path = self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0]]);
		$shortcut = new Path([new Node(0, 64, 0), new Node(3, 64, 0)], new Vector3(3, 64, 0), PathResult::REACHED);

		self::assertTrue($path->splice(0, $shortcut, 3)->equals(self::path([[0, 64, 0], [3, 64, 0]])));
	}

	public function testCanSpliceAgreesWithSplice() : void{
		$cursor = new PathCursor(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0]]), 2);
		$detour = new Path([new Node(1, 64, 0), new Node(2, 64, 0)], new Vector3(2, 64, 0), PathResult::REACHED);

		self::assertTrue($cursor->canSplice(1, $detour, 2));
		self::assertFalse($cursor->canSplice(1, $detour, 1), "rejoin is not after the splice");
		self::assertFalse($cursor->canSplice(0, $detour), "behind the mob");
		self::assertFalse($cursor->canSplice(2, $detour), "starts elsewhere");
		self::assertFalse($cursor->canSplice(1, new Path([], new Vector3(0, 0, 0), PathResult::BLOCKED)));
	}

	public function testInvalidationIsTheNodesAColumnServes() : void{
		$path = self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0], [4, 64, 0]]);

		$invalidation = $path->findInvalidation(2, 64, 0);
		self::assertNotNull($invalidation);
		self::assertSame([1, 3], [$invalidation->first, $invalidation->last], "the node and its ring neighbours");
		self::assertNull($path->findInvalidation(2, 80, 0), "above the corridor");
		self::assertNull($path->findInvalidation(2, 64, 9));
		self::assertTrue($path->isInCorridor(2, 64, 0));
	}

	public function testInvalidationForgetsWalkedNodes() : void{
		$cursor = new PathCursor(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0], [4, 64, 0]]), 3);

		self::assertNull($cursor->findInvalidation(0, 64, 0));
		self::assertNotNull($cursor->findInvalidation(2, 64, 0), "the node being left is still there");
	}

	public function testRepairBetweenTheNodesAroundTheInvalidation() : void{
		$cursor = new PathCursor(self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0], [4, 64, 0], [5, 64, 0]]), 1);

		$repair = $cursor->planRepair(new PathInvalidation(3, 4));
		self::assertSame([2, 5], [$repair?->from, $repair?->rejoin]);
	}

	public function testNoRepairWhenTheMobMayBeStandingOnTheInvalidation() : void{
		$path = self::path([[0, 64, 0], [1, 64, 0], [2, 64, 0], [3, 64, 0], [4, 64, 0]]);
		self::assertNull((new PathCursor($path))->planRepair(new PathInvalidation(0, 1)), "the start");

		$cursor = new PathCursor($path, 3);
		self::assertNull($cursor->planRepair(new PathInvalidation(2, 3)), "the node just left");
		self::assertNull($cursor->planRepair(new PathInvalidation(1, 2)), "behind the mob");
		self::assertSame(2, $cursor->planRepair(new PathInvalidation(3, 3))?->from, "the node being walked to"); //3 is next
	}

	public function testEquals() : void{
		$a = self::path([[0, 64, 0], [1, 64, 0]]);
		self::assertTrue($a->equals(self::path([[0, 64, 0], [1, 64, 0]])));
		self::assertFalse($a->equals(self::path([[0, 64, 0], [1, 65, 0]])));
		self::assertFalse($a->equals(self::path([[0, 64, 0]])));
	}
}
