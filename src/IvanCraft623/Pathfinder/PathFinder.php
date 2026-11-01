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

use Closure;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\math\Vector3;
use function array_reverse;
use function floor;
use function min;
use const INF;

class PathFinder {

	public const FUDGING = 1.5;

	/**
	 * @phpstan-param (Closure() : bool)|null $isCancelled checked between nodes
	 */
	public static function search(NodeEvaluator $evaluator, BlockGetter $blockGetter, SearchRequest $request, ?Closure $isCancelled = null) : Path{
		$evaluator->prepare($blockGetter, $request->start);
		try{
			$target = $request->target;
			return self::actuallyFindPath(
				$evaluator,
				$evaluator->getStart(),
				$evaluator->getGoal(floor($target->x), floor($target->y), floor($target->z)),
				$request->maxVisitedNodes,
				$request->maxDistanceFromStart,
				$request->reachRange,
				$isCancelled
			);
		}finally{
			$evaluator->done();
		}
	}

	/**
	 * Attempts to find a path from the start node to the target.
	 */
	public static function actuallyFindPath(
		NodeEvaluator $evaluator,
		Node $startNode,
		Target $target,
		int $maxVisitedNodes,
		float $maxDistanceFromStart,
		int $reachRange,
		?Closure $isCancelled = null
	) : Path {
		$openSet = new BinaryHeap();

		$startNode->g = 0.0;
		$startNode->h = self::getBestHFor($startNode, $target);
		$startNode->f = $startNode->h;

		$openSet->insert($startNode);

		$visitedNodes = 0;

		$maxDistanceFromStartSqr = $maxDistanceFromStart ** 2;

		$result = PathResult::BLOCKED;

		while (!$openSet->isEmpty()) {
			if ($isCancelled !== null && $isCancelled()) {
				$result = PathResult::CANCELLED;
				break;
			}
			if (++$visitedNodes >= $maxVisitedNodes) {
				$result = PathResult::EXHAUSTED;

				break;
			}

			$current = $openSet->pop();
			$current->closed = true;

			if ($current->distanceManhattan($target) <= $reachRange) {
				$target->setReached();
				$result = PathResult::REACHED;

				break;
			}

			if ($current->distanceSquared($startNode) < $maxDistanceFromStartSqr) {
				foreach ($evaluator->getNeighbors($current) as $neighbor) {
					$distance = $current->distance($neighbor);
					$neighbor->walkedDistance = $current->walkedDistance + $distance;
					$newNeighborG = $current->g + $distance + $neighbor->costMalus;

					if ($neighbor->walkedDistance < $maxDistanceFromStart && (!$neighbor->inOpenSet() || $newNeighborG < $neighbor->g)) {
						$neighbor->cameFrom = $current;
						$neighbor->g = $newNeighborG;
						$neighbor->h = self::getBestHFor($neighbor, $target) * self::FUDGING;

						if ($neighbor->inOpenSet()) {
							$openSet->changeCost($neighbor, $neighbor->g + $neighbor->h);
						} else {
							$neighbor->f = $neighbor->g + $neighbor->h;
							$openSet->insert($neighbor);
						}
					}
				}
			}
		}

		return self::reconstructPath($target->getBestNode(), $target->asVector3(), $result, $evaluator);
	}

	/**
	 * @param Target[] $targets
	 */
	public static function getBestH(Node $node, array $targets) : float{
		$bestH = INF;
		foreach ($targets as $target) {
			$bestH = min($bestH, self::getBestHFor($node, $target));
		}

		return $bestH;
	}

	/**
	 * Single-target heuristic; avoids the array allocation in {@link getBestH()}.
	 */
	private static function getBestHFor(Node $node, Target $target) : float{
		$h = $node->distance($target);
		$target->updateBest($h, $node);

		return $h;
	}

	private static function reconstructPath(Node $startNode, Vector3 $target, PathResult $result, NodeEvaluator $evaluator) : Path{
		/** @var Node[] $nodes */
		$nodes = [];
		$currentNode = $startNode;
		$nodes[] = $currentNode;

		while (($from = $currentNode->cameFrom) !== null) {
			/** @var Node $from */
			$currentNode = $from;
			$nodes[] = $from;
		}

		return new Path(array_reverse($nodes), $target, $result, $evaluator->getNodeWidth(), $evaluator->getNodeHeight());
	}
}
