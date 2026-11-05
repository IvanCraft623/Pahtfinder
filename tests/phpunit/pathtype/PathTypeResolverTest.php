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

namespace IvanCraft623\Pathfinder\pathtype;

use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\TestWorld;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;

final class PathTypeResolverTest extends TestCase{
	private const FAR = [100.5, 64, 100.5];

	private static function far() : Vector3{
		return new Vector3(...self::FAR);
	}

	private static function zombie(bool $canPassDoors = false, bool $canOpenDoors = false, ?BlockPathTypeCostMap $costMap = null) : MovementProfile{
		return new MovementProfile(0.6, 1.95, costMap: $costMap ?? new BlockPathTypeCostMap(), canPassDoors: $canPassDoors, canOpenDoors: $canOpenDoors);
	}

	private static function golem() : MovementProfile{
		return new MovementProfile(1.4, 1.4);
	}

	public function testAFenceAnywhereInTheSpaceBlocksIt() : void{
		$world = (new TestWorld())
			->fill(-1, 63, -1, 2, 63, 2, VanillaBlocks::STONE())
			->set(1, 64, 1, VanillaBlocks::OAK_FENCE());
		self::assertSame(BlockPathType::FENCE, (new WalkPathTypeResolver())->getPathType($world->getBlockGetter(), self::golem(), 0, 64, 0, self::far()));
		self::assertSame(BlockPathType::FENCE, (new FlightPathTypeResolver())->getPathType($world->getBlockGetter(), self::golem(), 0, 64, 0, self::far()));
	}

	public function testDoorsDependOnTheProfile() : void{
		$world = (new TestWorld())
			->fill(-1, 63, -1, 1, 63, 1, VanillaBlocks::STONE())
			->set(0, 64, 0, VanillaBlocks::OAK_DOOR())
			->set(0, 65, 0, VanillaBlocks::OAK_DOOR()->setTop(true));
		$resolver = new WalkPathTypeResolver();

		self::assertSame(BlockPathType::WALKABLE_DOOR, $resolver->getPathType($world->getBlockGetter(), self::zombie(true, true), 0, 64, 0, self::far()));
		self::assertSame(BlockPathType::DOOR_WOOD_CLOSED, $resolver->getPathType($world->getBlockGetter(), self::zombie(true), 0, 64, 0, self::far()));

		$world->set(0, 64, 0, VanillaBlocks::OAK_DOOR()->setOpen(true));
		self::assertSame(BlockPathType::BLOCKED, $resolver->getPathType($world->getBlockGetter(), self::zombie(), 0, 64, 0, self::far()), "can't pass doors");
	}

	public function testRailsArePassableFromRails() : void{
		$world = (new TestWorld())
			->fill(-1, 63, -1, 3, 63, 1, VanillaBlocks::STONE())
			->set(0, 64, 0, VanillaBlocks::RAIL())
			->set(2, 64, 0, VanillaBlocks::RAIL());
		$resolver = new WalkPathTypeResolver();

		self::assertSame(BlockPathType::UNPASSABLE_RAIL, $resolver->getPathType($world->getBlockGetter(), self::zombie(), 0, 64, 0, self::far()));
		self::assertSame(BlockPathType::RAIL, $resolver->getPathType($world->getBlockGetter(), self::zombie(), 0, 64, 0, new Vector3(2.5, 64, 0.5)));
	}

	/**
	 * A wide walker takes the costliest of its free blocks, a flyer counts the space as open.
	 */
	public function testPartlyFlooredSpace() : void{
		//Floor under x=1 only: the corner column is over air, the other one over stone
		$world = (new TestWorld())->fill(1, 63, 0, 1, 63, 1, VanillaBlocks::STONE());

		self::assertSame(BlockPathType::WALKABLE, (new WalkPathTypeResolver())->getPathType($world->getBlockGetter(), self::golem(), 0, 64, 0, self::far()));
		self::assertSame(BlockPathType::OPEN, (new FlightPathTypeResolver())->getPathType($world->getBlockGetter(), self::golem(), 0, 64, 0, self::far()));
	}

	public function testImpassableCostWins() : void{
		$world = (new TestWorld())
			->fill(-1, 62, -1, 1, 62, 1, VanillaBlocks::STONE())
			->set(0, 63, 0, VanillaBlocks::WATER());
		$costMap = new BlockPathTypeCostMap();
		$costMap->setPathfindingMalus(BlockPathType::WATER, -1.0);

		self::assertSame(BlockPathType::WATER, (new WalkPathTypeResolver())->getPathType($world->getBlockGetter(), self::zombie(costMap: $costMap), 0, 63, 0, self::far()));
	}

	public function testAmphibiousWaterTouchingSolidsIsItsBorder() : void{
		$world = (new TestWorld())
			->fill(-2, 60, -2, 2, 60, 2, VanillaBlocks::STONE())
			->fill(-1, 61, -1, 1, 63, 1, VanillaBlocks::WATER());
		$resolver = new AmphibiousPathTypeResolver();

		self::assertSame(BlockPathType::WATER_BORDER, $resolver->getBlockPathType($world->getBlockGetter(), 0, 61, 0), "above the floor");
		self::assertSame(BlockPathType::WATER, $resolver->getBlockPathType($world->getBlockGetter(), 0, 62, 0));
	}
}
