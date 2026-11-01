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

namespace IvanCraft623\Pathfinder\task;

use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathfindingTestCase;
use IvanCraft623\Pathfinder\PathResult;
use pmmp\thread\ThreadSafeArray;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_serialize;

/**
 * Runs the task's worker and main thread sides on this thread, with every chunk it reads already sent.
 */
final class AsyncPathFinderTaskTest extends PathfindingTestCase{
	/** @phpstan-var \ArrayObject<int, string> messages logged during the test */
	private \ArrayObject $logged;

	protected function setUp() : void{
		$this->logged = new \ArrayObject();
		\GlobalLogger::set(new class($this->logged) extends \SimpleLogger{
			/** @phpstan-param \ArrayObject<int, string> $logged */
			public function __construct(private \ArrayObject $logged){
			}

			public function log($level, $message){
				$this->logged[] = $message;
			}
		});
	}

	protected function tearDown() : void{
		\GlobalLogger::set(new \SimpleLogger());
	}

	private ?Path $completed = null;

	private function task(string $serializedEvaluator, Vector3 $start, Vector3 $target) : AsyncPathFinderTask{
		$chunks = [];
		foreach (self::flatWorld()->getChunks() as $hash => $chunk) {
			$chunks[$hash] = FastChunkSerializer::serializeTerrain($chunk);
		}
		return new AsyncPathFinderTask(
			nodeEvaluator: $serializedEvaluator,
			start: igbinary_serialize($start) ?? throw new \AssertionError(),
			target: igbinary_serialize($target) ?? throw new \AssertionError(),
			worldId: 0,
			maxVisitedNodes: 2000,
			maxDistanceFromStart: 64,
			reachRange: 0,
			defaultChunks: ThreadSafeArray::fromArray($chunks),
			worldMinY: World::Y_MIN,
			worldMaxY: World::Y_MAX,
			onCompletion: function(Path $path) : void{
				$this->completed = $path;
			}
		);
	}

	private function walkerTask(Vector3 $start, Vector3 $target) : AsyncPathFinderTask{
		$evaluator = new WalkNodeEvaluator();
		self::setUpMob($evaluator, $start);
		return $this->task(igbinary_serialize($evaluator) ?? throw new \AssertionError(), $start, $target);
	}

	public function testFindsThePath() : void{
		$start = self::standingAt(8, 8);
		$target = self::standingAt(24, 8);
		$task = $this->walkerTask($start, $target);
		$task->onRun();
		$task->onCompletion();

		self::assertNotNull($this->completed);
		self::assertReached($this->completed, $target);
		self::assertCount(0, $this->logged);
	}

	public function testCancelledTaskSkipsTheSearch() : void{
		$task = $this->walkerTask(self::standingAt(8, 8), self::standingAt(24, 8));
		$task->cancel();
		$task->onRun();
		self::assertFalse($task->hasResult());

		$task->onCompletion();
		self::assertSame(PathResult::CANCELLED, $this->completed?->getPathResult());
		self::assertSame(0, $this->completed->getNodeCount());
	}

	/**
	 * Throwing out of onRun() would crash the worker, and with it the server.
	 */
	public function testFailedSearchIsReportedAndLogged() : void{
		$task = $this->task(igbinary_serialize("not an evaluator") ?? throw new \AssertionError(), self::standingAt(8, 8), self::standingAt(24, 8));
		$task->onRun();
		$task->onCompletion();

		self::assertSame(PathResult::FAILED, $this->completed?->getPathResult());
		self::assertSame(0, $this->completed->getNodeCount());
		$logged = $this->logged->getArrayCopy();
		self::assertCount(1, $logged);
		self::assertStringContainsString("Path search failed: Error: Call to a member function prepare() on string", $logged[0]);
	}
}
