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

use Closure;

use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathFinder;
use IvanCraft623\Pathfinder\PathResult;
use IvanCraft623\Pathfinder\world\ArrayChunkSource;
use IvanCraft623\Pathfinder\world\ChunkBlockGetter;
use IvanCraft623\Pathfinder\world\ChunkRequester;
use IvanCraft623\Pathfinder\world\RequestingChunkSource;

use pmmp\thread\ThreadSafeArray;
use pocketmine\math\Vector3;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\AsyncWorker;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_unserialize;

class AsyncPathFinderTask extends AsyncTask implements ChunkRequester {

	private const TLS_KEY_COMPLETION_CALLBACK = "completionCallback";

	/** Longest single wait for the main thread's answer, in microseconds; its notify ends the wait at once */
	private const CHUNK_WAIT_TIMEOUT = 50_000;

	/** Set by the main thread, unset by the worker once read */
	private string $missingChunkResult;

	private bool $cancelled = false;

	private ?string $failure = null; //what the search threw

	/**
	 * @phpstan-param ThreadSafeArray<int, string> $defaultChunks
	 * @phpstan-param Closure(Path $path) : void $onCompletion
	 */
	public function __construct(
		private string $nodeEvaluator,
		private string $start,
		private string $target,
		private int $worldId,
		private int $maxVisitedNodes,
		private float $maxDistanceFromStart,
		private int $reachRange,
		private ThreadSafeArray $defaultChunks,
		private int $worldMinY,
		private int $worldMaxY,
		Closure $onCompletion,
	){
		$this->storeLocal(self::TLS_KEY_COMPLETION_CALLBACK, $onCompletion);
	}

	/**
	 * The completion callback then gets an empty path with {@link PathResult::CANCELLED}, and the search is skipped
	 * if it has not started.
	 */
	public function cancel() : void{
		$this->cancelled = true;
	}

	public function onRun() : void{
		if($this->cancelled) {
			return;
		}

		//a throw here kills the worker and crashes the server
		try{
			$this->setResult($this->search());
		}catch(\Throwable $e){
			$this->failure = (string) $e;
		}
	}

	private function search() : Path{
		/** @var NodeEvaluator */
		$evaluator = igbinary_unserialize($this->nodeEvaluator);
		/** @var Vector3 */
		$start = igbinary_unserialize($this->start);
		/** @var Vector3 */
		$target = igbinary_unserialize($this->target);

		$chunks = new ArrayChunkSource();
		foreach($this->defaultChunks as $hash => $chunk) {
			World::getXZ($hash, $chunkX, $chunkZ);
			$chunks->setChunk($chunkX, $chunkZ, FastChunkSerializer::deserializeTerrain($chunk));
		}
		$blockGetter = new ChunkBlockGetter(new RequestingChunkSource($chunks, $this), $this->worldMinY, $this->worldMaxY);

		$evaluator->prepare($blockGetter, $start);

		return PathFinder::actuallyFindPath(
			$evaluator,
			$evaluator->getStart(),
			$evaluator->getGoal((int) $target->x, (int) $target->y, (int) $target->z),
			$this->maxVisitedNodes,
			$this->maxDistanceFromStart,
			$this->reachRange
		);
	}

	public function requestChunk(int $chunkX, int $chunkZ) : ?Chunk{
		$this->publishProgress(World::chunkHash($chunkX, $chunkZ));
		//Progress is otherwise only looked at once per tick
		AsyncWorker::getNotifier()->wakeupSleeper();

		/** @var string $chunk */
		$chunk = $this->synchronized(function() : string{
			while(!isset($this->missingChunkResult)) {
				$this->wait(self::CHUNK_WAIT_TIMEOUT);
			}

			$chunk = $this->missingChunkResult;
			unset($this->missingChunkResult);
			return $chunk;
		});

		return $chunk === "" ? null : FastChunkSerializer::deserializeTerrain($chunk);
	}

	public function onProgressUpdate($progress) : void{
		$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);

		/** @var int $progress */
		World::getXZ($progress, $chunkX, $chunkZ);
		$chunk = $world?->getChunk($chunkX, $chunkZ);
		$result = $chunk === null ? "" : FastChunkSerializer::serializeTerrain($chunk);

		$this->synchronized(function() use ($result) : void{
			$this->missingChunkResult = $result;
			$this->notify();
		});
	}

	public function onCompletion() : void{
		/** @phpstan-var Closure(Path) : void $callback */
		$callback = $this->fetchLocal(self::TLS_KEY_COMPLETION_CALLBACK);
		if($this->cancelled) {
			($callback)($this->emptyPath(PathResult::CANCELLED));
			return;
		}

		$result = $this->getResult();
		if(!$result instanceof Path) {
			\GlobalLogger::get()->error("Path search failed: " . ($this->failure ?? "no result"));
			($callback)($this->emptyPath(PathResult::FAILED));
			return;
		}
		($callback)($result);
	}

	private function emptyPath(PathResult $result) : Path{
		/** @var Vector3 $target */
		$target = igbinary_unserialize($this->target);
		return new Path([], $target, $result);
	}
}
