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
use IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\Pathfinder\world\ArrayChunkSource;
use IvanCraft623\Pathfinder\world\Terrain;
use pmmp\thread\ThreadSafeArray;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_serialize;

/**
 * Runs the task's worker and main thread sides on this thread, with every chunk it reads already sent.
 */
final class AsyncPathFinderTaskTest extends PathfindingTestCase{
	/** @phpstan-var \ArrayObject<int, string> */
	private \ArrayObject $logged;

	private ?Path $completed = null;

	protected function setUp() : void{
		$this->logged = new \ArrayObject();
	}

	private function task(string $serializedEvaluator, Vector3 $start, Vector3 $target) : AsyncPathFinderTask{
		$world = self::flatWorld();
		$sent = [];
		foreach ($world->getChunks() as $hash => $chunk) {
			$sent[$hash] = FastChunkSerializer::serializeTerrain($chunk);
		}
		return new AsyncPathFinderTask(
			$serializedEvaluator,
			igbinary_serialize(self::zombie()) ?? throw new \AssertionError(),
			igbinary_serialize(new SearchRequest($start, $target, 2000, 64, 0)) ?? throw new \AssertionError(),
			ThreadSafeArray::fromArray($sent),
			new Terrain(new ArrayChunkSource($world->getChunks()), World::Y_MIN, World::Y_MAX),
			new class($this->logged) extends \SimpleLogger{
				/** @phpstan-param \ArrayObject<int, string> $logged */
				public function __construct(private \ArrayObject $logged){
				}

				public function log($level, $message){
					$this->logged[] = $message;
				}
			},
			function(Path $path) : void{
				$this->completed = $path;
			}
		);
	}

	private function walkerTask(Vector3 $start, Vector3 $target) : AsyncPathFinderTask{
		$evaluator = new WalkNodeEvaluator();
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
		self::assertStringStartsWith('Path search failed: TypeError', $logged[0]);
		self::assertStringContainsString('must be of type IvanCraft623\\Pathfinder\\evaluator\\NodeEvaluator, string given', $logged[0]);
	}
}
