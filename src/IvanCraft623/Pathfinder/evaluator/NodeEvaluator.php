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

namespace IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\Node;
use IvanCraft623\Pathfinder\SearchContext;
use IvanCraft623\Pathfinder\Target;
use IvanCraft623\Pathfinder\utils\EnumSet;
use IvanCraft623\Pathfinder\world\BlockGetter;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use function floor;

/**
 * @phpstan-import-type BlockPosHash from World
 */
abstract class NodeEvaluator {

	protected SearchContext $context;

	/** @var array<BlockPosHash, Node> */
	protected array $nodes = [];

	/** @var EnumSet<BlockPathType>|null */
	protected ?EnumSet $pathTypes = null;

	public function prepare(SearchContext $context) : void{
		$this->context = $context;
		$this->nodes = [];
	}

	public function done() : void{
		unset($this->context);
		$this->nodes = [];
	}

	final protected function getPathfindingMalus(BlockPathType $pathType) : float{
		return $this->context->profile->costMap->getPathfindingMalus($pathType);
	}

	public function getNode(Vector3 $pos) : Node{
		return $this->getNodeAt((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z));
	}

	public function getNodeAt(int $x, int $y, int $z) : Node{
		if (!$this->context->blockGetter->isInWorld($x, $y, $z)) {
			throw new \InvalidArgumentException("Position ($x, $y, $z) is out of world bounds!");
		}

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

	/**
	 * @param Vector3 $mobPosition where the mob is, some types depend on it
	 */
	public abstract function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z, Vector3 $mobPosition) : BlockPathType;
}
