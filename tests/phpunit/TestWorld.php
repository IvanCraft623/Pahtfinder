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

use IvanCraft623\Pathfinder\world\AsyncBlockGetter;
use IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\block\Block;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * In-memory world; unset chunks read as air, like chunks the main thread could not send.
 */
final class TestWorld{
	/**
	 * @var Chunk[]
	 * @phpstan-var array<int, Chunk>
	 */
	private array $chunks = [];

	public function set(int $x, int $y, int $z, Block $block) : self{
		$hash = World::chunkHash($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
		$chunk = $this->chunks[$hash] ??= new Chunk([], true);
		$chunk->setBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, $block->getStateId());
		return $this;
	}

	/**
	 * Fills the box between both corners, inclusive.
	 */
	public function fill(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $block) : self{
		for ($x = $x1; $x <= $x2; ++$x) {
			for ($y = $y1; $y <= $y2; ++$y) {
				for ($z = $z1; $z <= $z2; ++$z) {
					$this->set($x, $y, $z, $block);
				}
			}
		}
		return $this;
	}

	/**
	 * A getter reading this world the way the worker does, with every chunk already sent.
	 */
	public function getBlockGetter() : BlockGetter{
		$chunks = $this->chunks;
		return new class($chunks) extends AsyncBlockGetter{
			/**
			 * @phpstan-param array<int, Chunk> $chunks
			 */
			public function __construct(private array $chunks){
				//Skips AsyncBlockGetter's constructor: there is no task to request chunks from
				BlockGetter::__construct(World::Y_MIN, World::Y_MAX);
			}

			protected function getChunkAt(int $x, int $z) : ?Chunk{
				return $this->chunks[World::chunkHash($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)] ?? null;
			}
		};
	}
}
