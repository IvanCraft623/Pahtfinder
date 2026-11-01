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

use IvanCraft623\Pathfinder\evaluator\FlightNodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;

/**
 * Following a target that moved by extending the path to it instead of searching from the mob again.
 */
final class SplicePathfindingTest extends PathfindingTestCase{
	/**
	 * @return array{Path, int} the path and how many nodes its search expanded
	 */
	private static function countedSearch(TestWorld $world, Vector3 $start, Vector3 $target, bool $startAtNode = false) : array{
		$expanded = 0;
		$path = (new AStar(new WalkNodeEvaluator(), self::zombie(), $world->getBlockGetter()))->search(
			new SearchRequest($start, $target, 2000, 64, 0, startAtNode: $startAtNode),
			function() use (&$expanded) : bool{
				++$expanded;
				return false;
			}
		);
		return [$path, $expanded];
	}

	/**
	 * @return array{Path, int, int} the spliced path, the splice index and how many nodes the continuation expanded
	 */
	private static function walkThenSplice(TestWorld $world, Vector3 $oldTarget, Vector3 $newTarget) : array{
		[$path] = self::countedSearch($world, self::standingAt(2, 5), $oldTarget);
		self::assertReached($path, $oldTarget);
		for ($i = 0; $i < 4; ++$i) {
			$path->advance();
		}

		$index = $path->getClosestNodeIndex($newTarget);
		[$continuation, $expanded] = self::countedSearch($world, $path->getNode($index), $newTarget, true);
		$spliced = $path->splice($index, $continuation);

		self::assertSame(4, $spliced->getNextNodeIndex());
		for ($i = 0; $i <= $index; ++$i) {
			self::assertTrue($spliced->getNode($i)->equals($path->getNode($i)), "keeps node $i");
		}
		return [$spliced, $index, $expanded];
	}

	public function testFollowingATargetThatMovedABlock() : void{
		$world = self::flatWorld();
		$newTarget = self::standingAt(23, 6);
		[$spliced, , $expanded] = self::walkThenSplice($world, self::standingAt(22, 5), $newTarget);

		self::assertReached($spliced, $newTarget);
		self::assertContiguous($spliced);
		[, $fullExpanded] = self::countedSearch($world, self::standingAt(6, 5), $newTarget);
		self::assertLessThan($fullExpanded / 4, $expanded, "much less than a new path from the mob");
	}

	/**
	 * Evaluators place a mob standing at a position differently, so continuations start at the node itself.
	 */
	#[DataProvider("wideMobs")]
	public function testWideMobsContinueFromTheNode(NodeEvaluator $evaluator, MovementProfile $profile) : void{
		$world = self::flatWorld();
		$path = (new AStar($evaluator, $profile, $world->getBlockGetter()))->search(new SearchRequest(self::standingAt(3, 5), self::standingAt(20, 5), 2000, 64, 0));
		self::assertGreaterThan(4, $path->getNodeCount());

		for ($index = 1; $index < $path->getNodeCount(); ++$index) {
			$continuation = (new AStar($evaluator, $profile, $world->getBlockGetter()))->search(new SearchRequest($path->getNode($index), self::standingAt(22, 7), 2000, 64, 0, startAtNode: true));
			self::assertTrue($continuation->getNode(0)->equals($path->getNode($index)), "starts at node $index");
			$path->splice($index, $continuation);
		}
	}

	/**
	 * @phpstan-return \Generator<string, array{NodeEvaluator, MovementProfile}>
	 */
	public static function wideMobs() : \Generator{
		yield "walker" => [new WalkNodeEvaluator(), new MovementProfile(1.4, 2.9, maxUpStep: 1.0)];
		yield "flyer" => [new FlightNodeEvaluator(), new MovementProfile(1.6, 1.0)];
	}

	public function testFollowingATargetBehindAWall() : void{
		$world = self::flatWorld()->fill(25, 64, 3, 25, 65, 7, VanillaBlocks::STONE());
		$newTarget = self::standingAt(27, 5);
		[$spliced] = self::walkThenSplice($world, self::standingAt(22, 5), $newTarget);

		self::assertReached($spliced, $newTarget);
		self::assertContiguous($spliced);
	}
}
