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

namespace IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\Node;
use IvanCraft623\Pathfinder\Target;
use IvanCraft623\Pathfinder\world\BlockGetter;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use function floor;

/**
 * @phpstan-import-type BlockPosHash from World
 */
abstract class NodeEvaluator {

	protected BlockPathTypeCostMap $pathTypeCostMap;

	protected BlockGetter $blockGetter;

	protected Vector3 $startPosition;

	/** @var array<BlockPosHash, Node> */
	protected array $nodes = [];

	public function __construct(?BlockPathTypeCostMap $pathTypeCostMap = null) {
		$this->pathTypeCostMap = $pathTypeCostMap ?? new BlockPathTypeCostMap(); //empty one
	}

	public function prepare(BlockGetter $blockGetter, Vector3 $startPosition) : void{
		$this->blockGetter = $blockGetter;
		$this->startPosition = $startPosition;

		$this->nodes = [];
	}

	public function done() : void{
		unset($this->blockGetter);
		unset($this->startPosition);
		unset($this->nodes);
	}

	public function getNode(Vector3 $pos) : Node{
		return $this->getNodeAt((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z));
	}

	public function getNodeAt(int $x, int $y, int $z) : Node{
		$hash = Node::createHash($x, $y, $z);
		if (!isset($this->nodes[$hash])) {
			$this->nodes[$hash] = new Node($x, $y, $z);
		}
		return $this->nodes[$hash];
	}

	public abstract function getStart() : Node;

	public abstract function getGoal(float $x, float $y, float $z) : Target;

	protected function getTargetFromNode(Node $node) : Target {
		return Target::fromObject($node);
	}

	/**
	 * @return Node[]
	 */
	public abstract function getNeighbors(Node $node) : array;

	public abstract function getCachedBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType;

	public abstract function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType;
}
