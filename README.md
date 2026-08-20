<div align="center">
  <h1> 🗺️ Pathfinder 🧭</h1>
  <p>Pathfinder oriented for use with mobs for pocketmine</p>
</div>

## 📥 Installation

This is a **virion** (library), not a plugin. It is compiled directly into your
plugin at build time. Your plugin code just uses the `IvanCraft623\Pathfinder` namespace.

### Composer + pharynx (recommended)

Add the virion as a composer dependency in your plugin's `composer.json`:

```json
{
    "require-dev": {
        "ivancraft623/pathfinder": "dev-main"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/IvanCraft623/Pathfinder"
        }
    ]
}
```

Download `pharynx.phar` from https://github.com/SOF3/pharynx/releases and build
your plugin with it. The `-c` flag runs `composer install` and automatically
shades every installed virion (any dependency whose `composer.json` declares
`extra.virion`) into your plugin:

```
php pharynx.phar -i path/to/plugin -c -p my-plugin.phar
```

## 🧠 Usage

### Finding a path

Both `PathFinder::findPath()` (sync, main thread) and `PathFinder::findPathAsync()`
(async worker + main-thread callback) always return a `Path` — even on failure.

```php
use IvanCraft623\Pathfinder\PathFinder;
use IvanCraft623\Pathfinder\PathResult;
use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use pocketmine\math\Vector3;

$evaluator = new WalkNodeEvaluator();
$evaluator->setEntitySize($entity->getSize());
$evaluator->setEntityBoundingBox($entity->getBoundingBox());
$evaluator->setEntityOnGround($entity->isOnGround());
$evaluator->setMaxUpStep(0.6);
$evaluator->setMaxFallDistance(3);
$evaluator->setCanOpenDoors(true);

$path = PathFinder::findPath(
	$evaluator,
	$entity->getWorld(),
	$entity->getPosition(),
	new Vector3(100, 64, 100),
	maxVisitedNodes: 2000,      // stop searching after this many nodes
	maxDistanceFromStart: 32,   // do not search further than this from the start
	reachRange: 1               // consider the target reached within 1 block
);

if ($path->getPathResult() === PathResult::REACHED) {
	// feed $path into a Navigation, see "Navigation" below
}
```

### Reading the result

Both methods always produce a `Path`. Call `Path::getPathResult()` to learn how
the search ended:

- `PathResult::REACHED` — the target was reached within `reachRange`.
- `PathResult::EXHAUSTED` — the search stopped because `maxVisitedNodes` was
  reached; the path is a best-effort route to the closest node.
- `PathResult::BLOCKED` — the whole reachable area was searched and the target is
  unreachable; the path is a best-effort route to the closest node.

`Path::canReach()` is shorthand for `getPathResult() === PathResult::REACHED`.

## 🎛️ Evaluators

### Using evaluators

An evaluator is your mob's "mobility profile": how big it is, how high it can
step or fall, whether it opens doors, floats, or swims. Configure one with the
setters below and pass it to `findPath()` / `findPathAsync()` — the finder calls
`prepare()` and `done()` for you.

> When using `findPathAsync()` the evaluator is serialized to the worker thread,
> so only set thread-safe values on it (numbers, `EntitySizeInfo`, `AxisAlignedBB`).
>  Never store complex objects like `Entity` as reference.

You can also tweak traversal costs with a `BlockPathTypeCostMap` passed to the
constructor:

```php
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;

$costMap = new BlockPathTypeCostMap();
$costMap->setPathfindingMalus(BlockPathType::WATER, 2);

$evaluator = new WalkNodeEvaluator($costMap);
```

### Pre-made evaluators

| Evaluator | Use for |
| --- | --- |
| `NodeEvaluator` | Abstract base class. No entity awareness — implement everything yourself. |
| `EntityNodeEvaluator` | Abstract. Extend this for mobs: adds entity size, bounding box, on-ground, `setMaxUpStep()`, `setMaxFallDistance()`, `setCanOpenDoors()`, `setCanPassDoors()`, `setCanFloat()`, `setCanWalkOverFences()`, `setLiquidsThatCanStandOn()`. |
| `WalkNodeEvaluator` | Land mobs. 4 cardinal + 4 diagonal neighbors with up-step/jump and fall checks; handles fences, doors, rails and water borders. |
| `FlightNodeEvaluator` | Flying mobs. 6 cardinals + edges + corners; extra cost for flying close to the ground. |

All configurable on `EntityNodeEvaluator` (and subclasses):

```php
$evaluator->setEntitySize($entity->getSize());
$evaluator->setEntityBoundingBox($entity->getBoundingBox());
$evaluator->setEntityOnGround($entity->isOnGround());
$evaluator->setMaxUpStep(0.6);
$evaluator->setMaxFallDistance(3);
$evaluator->setCanOpenDoors(true);
$evaluator->setCanPassDoors(false);
$evaluator->setCanFloat(true);
$evaluator->setCanWalkOverFences(false);
```

### Building your own evaluator

Extend `NodeEvaluator` (or `EntityNodeEvaluator` for the entity helpers) and
implement the five abstract methods:

- `getStart() : Node` — the start node, usually derived from `$this->startPosition`.
- `getGoal(float $x, float $y, float $z) : Target` — the target node; typically
  `$this->getTargetFromNode($this->getNodeAt(...))`.
- `getNeighbors(Node $node) : array` — which nodes are reachable from `$node`.
- `getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType`
  — classify a block.
- `getCachedBlockPathType(...) : BlockPathType` — cached variant used during the
  search.

You have access to `$this->blockGetter`, `$this->startPosition`, `$this->nodes`
(the node cache) and `$this->pathTypeCostMap`, plus the `getNodeAt()` helper.

```php
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\Node;
use IvanCraft623\Pathfinder\Target;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\world\BlockGetter;
use function floor;

final class SimpleWalkEvaluator extends NodeEvaluator {

	public function getStart() : Node{
		return $this->getNodeAt(
			(int) floor($this->startPosition->x),
			(int) floor($this->startPosition->y),
			(int) floor($this->startPosition->z)
		);
	}

	public function getGoal(float $x, float $y, float $z) : Target{
		return $this->getTargetFromNode($this->getNodeAt((int) floor($x), (int) floor($y), (int) floor($z)));
	}

	/**
	 * @return Node[]
	 */
	public function getNeighbors(Node $node) : array{
		$neighbors = [];

		foreach ([[-1, 0, 0], [1, 0, 0], [0, 0, -1], [0, 0, 1], [0, -1, 0], [0, 1, 0]] as [$dx, $dy, $dz]) {
			$x = $node->x() + $dx;
			$y = $node->y() + $dy;
			$z = $node->z() + $dz;

			if (!$this->blockGetter->isInWorld($x, $y, $z)) {
				continue;
			}

			if ($this->getCachedBlockPathType($this->blockGetter, $x, $y, $z) !== BlockPathType::BLOCKED) {
				$neighbors[] = $this->getNodeAt($x, $y, $z);
			}
		}

		return $neighbors;
	}

	public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		return $blockGetter->getBlockAt($x, $y, $z)->isSolid() ? BlockPathType::BLOCKED : BlockPathType::OPEN;
	}

	public function getCachedBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		return $this->getBlockPathType($blockGetter, $x, $y, $z);
	}
}
```

## 🧭 Navigation

A minimal navigator that moves an entity along a `Path`: it computes the path
asynchronously, advances waypoint by waypoint, and jumps when the next node is
out of reach. See
[`PathNavigation`](https://github.com/IvanCraft623/MobPlugin/blob/main/src/IvanCraft623/MobPlugin/entity/ai/navigation/PathNavigation.php)
and
[`MoveControl`](https://github.com/IvanCraft623/MobPlugin/blob/main/src/IvanCraft623/MobPlugin/entity/ai/control/MoveControl.php)
in MobPlugin for a full production implementation (stuck detection, corner
cutting, path trimming, timeouts).

```php
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathFinder;
use IvanCraft623\Pathfinder\evaluator\EntityNodeEvaluator;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use function sqrt;

final class Navigator {

	private ?Path $path = null;

	public function __construct(
		private Entity $entity,
		private EntityNodeEvaluator $evaluator,
		private float $speed = 0.4
	){}

	public function moveTo(Vector3 $target) : void{
		$this->evaluator->setEntitySize($this->entity->getSize());
		$this->evaluator->setEntityBoundingBox($this->entity->getBoundingBox());
		$this->evaluator->setEntityOnGround($this->entity->isOnGround());

		PathFinder::findPathAsync(
			function (Path $path) : void{
				$this->path = $path;
			},
			$this->evaluator,
			$this->entity->getWorld(),
			$this->entity->getPosition(),
			$target,
			maxVisitedNodes: 2000,
			maxDistanceFromStart: 32,
			reachRange: 1
		);
	}

	public function tick() : void{
		if ($this->path === null || $this->path->isDone()) {
			$this->entity->setMotion(Vector3::zero());

			return;
		}

		$pos = $this->entity->getPosition();
		$nodePos = $this->path->getNextNodePos();

		// Advance once the entity is close enough to the waypoint
		if ($pos->distanceSquared($nodePos->add(0.5, 0, 0.5)) < 1) {
			$this->path->advance();

			if ($this->path->isDone()) {
				return;
			}
			$nodePos = $this->path->getNextNodePos();
		}

		// Move towards the waypoint
		$dx = $nodePos->x + 0.5 - $pos->x;
		$dy = $nodePos->y - $pos->y;
		$dz = $nodePos->z + 0.5 - $pos->z;
		$horizontal = sqrt($dx * $dx + $dz * $dz);

		if ($horizontal > 0.001) {
			$this->entity->setMotion(new Vector3($dx / $horizontal * $this->speed, 0, $dz / $horizontal * $this->speed));
		}

		// Jump if the next node is higher than the mob can step
		if ($dy > 0.5 && $this->entity->isOnGround()) {
			$this->entity->setMotion($this->entity->getMotion()->add(0, 0.4, 0));
		}
	}

	public function isDone() : bool{
		return $this->path === null || $this->path->isDone();
	}

	public function stop() : void{
		$this->path = null;
	}
}
```

### Putting it together

```php
$evaluator = new WalkNodeEvaluator(); // or FlightNodeEvaluator for flying mobs
$evaluator->setEntitySize($entity->getSize());
$evaluator->setEntityBoundingBox($entity->getBoundingBox());
$evaluator->setEntityOnGround($entity->isOnGround());
$evaluator->setMaxUpStep(0.6);
$evaluator->setMaxFallDistance(3);
$evaluator->setCanOpenDoors(true);

$navigator = new Navigator($entity, $evaluator);

$navigator->moveTo(new Vector3(100, 64, 100));

// then call $navigator->tick() every game tick while the mob should move
```
