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

namespace IvanCraft623\Pathfinder\world;

use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Answers chunk requests from a fixed set, as the main thread would, counting them.
 *
 * @phpstan-import-type ChunkPosHash from World
 */
final class CountingChunkRequester implements ChunkRequester{
	/**
	 * @var int[] World::chunkHash() => times requested
	 * @phpstan-var array<ChunkPosHash, int>
	 */
	public array $requests = [];

	/**
	 * @param Chunk[] $chunks World::chunkHash() => chunk the main thread has loaded
	 * @phpstan-param array<ChunkPosHash, Chunk> $chunks
	 */
	public function __construct(private array $chunks = []){
	}

	public function requestChunk(int $chunkX, int $chunkZ) : ?Chunk{
		$hash = World::chunkHash($chunkX, $chunkZ);
		$this->requests[$hash] = ($this->requests[$hash] ?? 0) + 1;
		return $this->chunks[$hash] ?? null;
	}
}
