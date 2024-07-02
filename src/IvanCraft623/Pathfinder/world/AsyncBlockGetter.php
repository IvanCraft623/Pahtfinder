<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\Pathfinder\world;

use IvanCraft623\Pathfinder\task\AsyncPathFinderTask;

use pocketmine\server\Server;
use pocketmine\block\Block;
use pocketmine\block\Door;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\format\Chunk;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\math\Vector3;
use pocketmine\math\Facing;

use ReflectionProperty;

/**
 * @phpstan-import-type ChunkPosHash from World
 * @phpstan-import-type BlockPosHash from World
 */
class AsyncBlockGetter extends BlockGetter{

	private bool $inDynamicStateRecalculation = false;

	/**
	 * @var Chunk[]
	 * @phpstan-var array<ChunkPosHash, ?Chunk>
	 */
	private array $chunks = [];

	/**
	 * @var Block[]
	 * @phpstan-var array<BlockPosHash, Block>
	 */
	private array $blocksCache;

	public function __construct(protected AsyncPathFinderTask $task, int $minY, int $maxY){
		parent::__construct($minY, $maxY);
	}

	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true) : Block{
		$blockHash = World::blockHash($x, $y, $z);
		if ($cached && isset($this->blocksCache[$blockHash])) {
			return $this->blocksCache[$blockHash];
		}

		if(!$this->isInWorld($x, $y, $z)) {
			$block = VanillaBlocks::AIR();
			$addToCache = false;
		} elseif (($chunk = $this->getChunkAt($x, $z)) === null) {
			$block = VanillaBlocks::AIR();
		} else {
			$block = RuntimeBlockStateRegistry::getInstance()->fromStateId(
				$chunk->getBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK)
			);
		}

		$this->positionBlock($block, new Position($x, $y, $z, null));

		if($this->inDynamicStateRecalculation) {
			//this ensures that it's impossible for dynamic state properties to recursively depend on each other.
			$addToCache = false;
		} else {
			$this->inDynamicStateRecalculation = true;
			$replacement = $this->readStateFromWorld($block);
			if($replacement !== $block){
				$this->positionBlock($replacement, clone $block->getPosition());
				$block = $replacement;
			}
			$this->inDynamicStateRecalculation = false;
		}

		if ($addToCache) {
			$this->blocksCache[$blockHash] = $block;
		}

		return $block;
	}

	protected function getChunkAt(int $x, int $z): ?Chunk {
		$chunkX = $x >> Chunk::COORD_BIT_SIZE;
		$chunkZ = $z >> Chunk::COORD_BIT_SIZE;
		$hash = World::chunkHash($chunkX, $chunkZ);

		//Check the chunk has been already loaded
		if(!array_key_exists($hash, $this->chunks)) {
			$this->task->publishProgress($hash);

			//Wait until an answer from the main thread
			while(!isset($this->task->missingChunkResult)) {
				if($this->task->isTerminated()) {
					return null;
				}
			}

			$chunk = $this->task->missingChunkResult;
			if($chunk === "") { //failed to get the chunk :c
				$this->chunks[$hash] = null;
			} else {
				$this->chunks[$hash] = FastChunkSerializer::deserializeTerrain($chunk);
			}
			unset($this->task->missingChunkResult);
		}

		return $this->chunks[$hash] ?? null;
	}

	public function setChunk(int $chunkX, int $chunkZ, Chunk $chunk): void {
		$this->chunks[World::chunkHash($chunkX, $chunkZ)] = $chunk;
	}

	private function positionBlock(Block $block, Position $position) : void{
		$property = new ReflectionProperty($block, "position");
		$property->setAccessible(true);
		$property->setValue($block, $position);
		$property->setAccessible(false);
	}

	/**
	 * Called when the block is created
	 *
	 * Replacement of {@link Block::readStateFromWorld()}, because that function cannot be executed
	 * since the World class is unavailable. 
	 *
	 * A replacement block may be returned. This only is useful if the block type changed due to reading
	 * others blocks data.
	 */
	private function readStateFromWorld(Block $block) : Block{
		if ($block instanceof Door) {
			$blockIsTop = $block->isTop();
			$other = $this->getBlockAtSide($block->getPosition(), $blockIsTop ? Facing::DOWN : Facing::UP);
			if($other instanceof Door && $other->hasSameTypeId($block)) {
				if ($blockIsTop) {
					$block->setFacing($other->getFacing())
						->setOpen($other->isOpen());
				} else {
					$block->setHingeRight($other->isHingeRight());
				}
			}
		}

		return $block;
	}

	/**
	 * Returns the Block on the side $side, works like Vector3::getSide()
	 *
	 * @return Block
	 */
	public function getBlockAtSide(Vector3 $position, int $side, int $step = 1) : Block{
		[$dx, $dy, $dz] = Facing::OFFSET[$side] ?? [0, 0, 0];
		return $this->getBlockAt(
			(int) $position->x + ($dx * $step),
			(int) $position->y + ($dy * $step),
			(int) $position->z + ($dz * $step)
		);
	}
}
