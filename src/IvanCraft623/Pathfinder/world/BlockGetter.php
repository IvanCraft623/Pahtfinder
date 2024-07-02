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

use pocketmine\block\Block;
use pocketmine\utils\Limits;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;

abstract class BlockGetter {

	public function __construct(protected int $minY, protected int $maxY){
	}

	abstract public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true) : Block;

	public function getBlock(Vector3 $pos, bool $cached = true, bool $addToCache = true): Block {
		return $this->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $cached, $addToCache);
	}

	public function isInWorld(int $x, int $y, int $z) : bool{
		return (
			$x <= Limits::INT32_MAX && $x >= Limits::INT32_MIN &&
			$y < $this->maxY && $y >= $this->minY &&
			$z <= Limits::INT32_MAX && $z >= Limits::INT32_MIN
		);
	}

	/**
	 * @return Block[]
	 * @phpstan-return list<Block>
	 */
	public function getCollisionBlocks(AxisAlignedBB $bb, bool $targetFirst = false) : array{
		$minX = (int) floor($bb->minX - 1);
		$minY = (int) floor($bb->minY - 1);
		$minZ = (int) floor($bb->minZ - 1);
		$maxX = (int) floor($bb->maxX + 1);
		$maxY = (int) floor($bb->maxY + 1);
		$maxZ = (int) floor($bb->maxZ + 1);

		$collides = [];

		if($targetFirst){
			for($z = $minZ; $z <= $maxZ; ++$z){
				for($x = $minX; $x <= $maxX; ++$x){
					for($y = $minY; $y <= $maxY; ++$y){
						$block = $this->getBlockAt($x, $y, $z);
						if($block->collidesWithBB($bb)){
							return [$block];
						}
					}
				}
			}
		}else{
			for($z = $minZ; $z <= $maxZ; ++$z){
				for($x = $minX; $x <= $maxX; ++$x){
					for($y = $minY; $y <= $maxY; ++$y){
						$block = $this->getBlockAt($x, $y, $z);
						if($block->collidesWithBB($bb)){
							$collides[] = $block;
						}
					}
				}
			}
		}

		return $collides;
	}
}
