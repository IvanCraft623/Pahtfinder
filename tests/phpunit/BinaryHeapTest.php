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

use PHPUnit\Framework\TestCase;

final class BinaryHeapTest extends TestCase{
	private static function node(int $x, float $f) : Node{
		$node = new Node($x, 0, 0);
		$node->f = $f;
		return $node;
	}

	public function testPopsInCostOrder() : void{
		$heap = new BinaryHeap();
		foreach ([5.0, 1.0, 4.0, 2.0, 3.0, 0.5] as $x => $f) {
			$heap->insert(self::node($x, $f));
		}

		$costs = [];
		while (!$heap->isEmpty()) {
			$node = $heap->pop();
			self::assertFalse($node->inOpenSet());
			$costs[] = $node->f;
		}
		self::assertSame([0.5, 1.0, 2.0, 3.0, 4.0, 5.0], $costs);
	}

	public function testInsertingTwiceThrows() : void{
		$heap = new BinaryHeap();
		$node = $heap->insert(self::node(0, 1.0));

		$this->expectException(\InvalidArgumentException::class);
		$heap->insert($node);
	}

	public function testChangeCostReorders() : void{
		$heap = new BinaryHeap();
		$a = $heap->insert(self::node(0, 1.0));
		$b = $heap->insert(self::node(1, 2.0));
		$c = $heap->insert(self::node(2, 3.0));

		$heap->changeCost($c, 0.5);
		self::assertSame($c, $heap->pop());

		$heap->changeCost($a, 10.0);
		self::assertSame($b, $heap->pop());
		self::assertSame($a, $heap->pop());
	}

	public function testRemove() : void{
		$heap = new BinaryHeap();
		$nodes = [];
		foreach ([3.0, 1.0, 2.0, 5.0, 4.0] as $x => $f) {
			$nodes[] = $heap->insert(self::node($x, $f));
		}

		$heap->remove($nodes[2]);
		self::assertFalse($nodes[2]->inOpenSet());

		$costs = [];
		while (!$heap->isEmpty()) {
			$costs[] = $heap->pop()->f;
		}
		self::assertSame([1.0, 3.0, 4.0, 5.0], $costs);
	}
}
