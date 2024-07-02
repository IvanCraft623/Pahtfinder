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

use pocketmine\server\Server;
use pocketmine\block\Block;
use pocketmine\world\World;

class SyncBlockGetter extends BlockGetter{

	public function __construct(protected World $world){
		parent::__construct($world->getMinY(), $world->getMaxY());
	}

	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true): Block{
		return $this->world->getBlockAt($x, $y, $z, $cached, $addToCache);
	}
}
