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

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class ChunkSourceTest extends TestCase{
	public function testMissingChunksAreRequestedOnce() : void{
		$loaded = new Chunk([], true);
		$requester = new CountingChunkRequester([World::chunkHash(3, 3) => $loaded]);
		$source = new RequestingChunkSource(new ArrayChunkSource(), $requester);

		for ($i = 0; $i < 2; ++$i) {
			self::assertSame($loaded, $source->getChunk(3, 3));
			self::assertNull($source->getChunk(4, 4), "not loaded on the main thread");
		}

		self::assertSame([World::chunkHash(3, 3) => 1, World::chunkHash(4, 4) => 1], $requester->requests);
	}

	/**
	 * Sent chunks used to be evicted past 64, then requested again.
	 */
	public function testManySentChunksAreKept() : void{
		$chunks = [];
		for ($x = 0; $x < 10; ++$x) {
			for ($z = 0; $z < 10; ++$z) {
				$chunks[World::chunkHash($x, $z)] = new Chunk([], true);
			}
		}
		$requester = new CountingChunkRequester();
		$getter = new ChunkBlockGetter(new RequestingChunkSource(new ArrayChunkSource($chunks), $requester), World::Y_MIN, World::Y_MAX);

		for ($x = 0; $x < 10; ++$x) {
			for ($z = 0; $z < 10; ++$z) {
				$getter->getBlockAt($x << Chunk::COORD_BIT_SIZE, 64, $z << Chunk::COORD_BIT_SIZE, false);
			}
		}
		self::assertSame([], $requester->requests);
	}

	public function testGetterReadsRequestedChunks() : void{
		$chunk = new Chunk([], true);
		$chunk->setBlockStateId(5, 64, 5, VanillaBlocks::STONE()->getStateId());
		$requester = new CountingChunkRequester([World::chunkHash(-1, 2) => $chunk]);
		$getter = new ChunkBlockGetter(new RequestingChunkSource(new ArrayChunkSource(), $requester), World::Y_MIN, World::Y_MAX);

		$x = -16 + 5;
		$z = 32 + 5;
		self::assertTrue($getter->getBlockAt($x, 64, $z)->isSameState(VanillaBlocks::STONE()));
		self::assertTrue($getter->getBlockAt($x, 65, $z)->isSameState(VanillaBlocks::AIR()));
		self::assertTrue($getter->getBlockAt(100, 64, 100)->isSameState(VanillaBlocks::AIR()), "unavailable chunk");
		self::assertSame(1, $requester->requests[World::chunkHash(-1, 2)]);
	}

	public function testOutOfWorldReadsAsAirWithoutRequests() : void{
		$requester = new CountingChunkRequester();
		$getter = new ChunkBlockGetter(new RequestingChunkSource(new ArrayChunkSource(), $requester), World::Y_MIN, World::Y_MAX);

		self::assertTrue($getter->getBlockAt(0, World::Y_MAX, 0)->isSameState(VanillaBlocks::AIR()));
		self::assertTrue($getter->getBlockAt(0, World::Y_MIN - 1, 0)->isSameState(VanillaBlocks::AIR()));
		self::assertSame([], $requester->requests);
	}

	public function testBlocksKnowTheirPosition() : void{
		$getter = new ChunkBlockGetter(new ArrayChunkSource(), World::Y_MIN, World::Y_MAX);
		$position = $getter->getBlockAt(7, 70, -9)->getPosition();
		self::assertSame([7.0, 70.0, -9.0], [(float) $position->x, (float) $position->y, (float) $position->z]);
	}
}
