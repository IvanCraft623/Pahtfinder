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

Searches run through a `SearchExecutor`: `AsyncSearchExecutor` searches on a worker
(asking the main thread for any chunk it was not sent), `SyncSearchExecutor` on the
calling thread. Both return a `PathSearch`, whose promise always resolves with a `Path`.

```php
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathResult;
use IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\Pathfinder\execution\AsyncSearchExecutor;
use IvanCraft623\Pathfinder\world\Terrain;
use pocketmine\math\Vector3;

$executor = new AsyncSearchExecutor($server->getAsyncPool(), $plugin->getLogger());

$profile = new MovementProfile(
	width: $entity->getSize()->getWidth(),
	height: $entity->getSize()->getHeight(),
	canOpenDoors: true
);

$search = $executor->search(new WalkNodeEvaluator(), $profile, Terrain::fromWorld($entity->getWorld()), new SearchRequest(
	$entity->getPosition(),
	new Vector3(100, 64, 100),
	maxVisitedNodes: 2000,      // stop searching after this many nodes
	maxDistanceFromStart: 32,   // do not search further than this from the start
	reachRange: 1,              // consider the target reached within 1 block
	onGround: $entity->isOnGround()
));
$search->getPromise()->onCompletion(function(Path $path) : void{
	if ($path->getPathResult() === PathResult::REACHED) {
		// feed $path into a Navigation, see "Navigation" below
	}
}, static function() : void{});

// $search->cancel() stops it; the promise then resolves with PathResult::CANCELLED
```

### Reading the result

Both methods always produce a `Path`. Call `Path::getPathResult()` to learn how
the search ended:

- `PathResult::REACHED` — the target was reached within `reachRange`.
- `PathResult::EXHAUSTED` — the search stopped because `maxVisitedNodes` was
  reached; the path is a best-effort route to the closest node.
- `PathResult::BLOCKED` — the whole reachable area was searched and the target is
  unreachable; the path is a best-effort route to the closest node.
- `PathResult::CANCELLED` — `PathSearch::cancel()` was called; the path is empty.
- `PathResult::FAILED` — the asynchronous search threw; the error is logged and the
  path is empty.

`Path::canReach()` is shorthand for `getPathResult() === PathResult::REACHED`.

### Following a path

A `Path` never changes: it is what a search found. `PathCursor` is a path and how far
along it the mob is. It advances (`advance()`, `isDone()`, `getNextNode()`) and answers
what depends on the progress, like which changed blocks still matter. `withNodes()`
makes the path through other nodes, e.g. adjusted to the mob, and `withPath()` keeps
the progress on it.

### Following a moving target

When the target moved a little, search from the remaining node closest to the new
target and splice the result on, keeping the walking progress. `startAtNode` starts
the search at that node itself, not where a mob there would stand; a small node
budget keeps a failed extension cheap.

```php
$index = $cursor->getClosestNodeIndex($newTarget);
$executor->search($evaluator, $profile, $terrain, new SearchRequest(
	$cursor->getPath()->getNode($index),
	$newTarget,
	maxVisitedNodes: 500,
	maxDistanceFromStart: 32,
	startAtNode: true
))->getPromise()->onCompletion(function(Path $continuation) use ($cursor, $index) : void{
	if ($continuation->canReach()) {
		$this->cursor = $cursor->splice($index, $continuation);
	}
}, static function() : void{});
```

A spliced path keeps the old route, so search from the mob again every few
splices. `PathCursor::splice()` throws if the continuation does not start at that node
or the node is behind the mob; `canSplice()` checks both.

### Repairing a path after a block change

`PathCursor::findInvalidation()` returns the nodes still to be walked that a changed block
matters to (`PathInvalidation::union()` merges several), and `planRepair()` the node to
search from and the node to rejoin. Search between them with `reachRange: 0` and
`splice()` the result in; the rest of the old path is kept. Without a rejoin node
the invalidated nodes reach the end of the path, so search to the target.

```php
$invalidation = $cursor->findInvalidation($x, $y, $z);
$repair = $invalidation === null ? null : $cursor->planRepair($invalidation);
if ($repair !== null) {
	$path = $cursor->getPath();
	$goal = $repair->rejoin === null ? $path->getTarget() : $path->getNode($repair->rejoin);
	$executor->search($evaluator, $profile, $terrain, new SearchRequest(
		$path->getNode($repair->from),
		$goal,
		maxVisitedNodes: 300,
		maxDistanceFromStart: 24,
		reachRange: $repair->rejoin === null ? $reach : 0,
		startAtNode: true
	))->getPromise()->onCompletion(function(Path $bridge) use ($cursor, $repair) : void{
		if ($bridge->canReach()) {
			$this->cursor = $cursor->splice($repair->from, $bridge, $repair->rejoin);
		}
	}, static function() : void{});
}
```

`planRepair()` returns `null` when the invalidation touches the node the mob just left:
search from the mob again.

## 🎛️ Evaluators

### Using evaluators

An evaluator is how a mob moves: walking, flying or swimming. What the mob is
goes in a `MovementProfile`: its size, how high it can step or fall, whether it
opens doors, floats, walks over fences or stands on fluids, and its traversal
costs. Pass both to a `SearchExecutor`; the search calls `prepare()` and `done()`
for you.

> With `AsyncSearchExecutor` the evaluator and the profile are serialized to the
> worker thread, so never keep references to complex objects like `Entity` in them.

Tweak traversal costs with a `BlockPathTypeCostMap` in the profile:

```php
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;

$costMap = new BlockPathTypeCostMap();
$costMap->setPathfindingMalus(BlockPathType::WATER, 2);

$profile = new MovementProfile(width: 0.6, height: 1.95, costMap: $costMap);
```

Presets such as `BlockPathTypeCostMap::amphibious()` give the costs a kind of
mob needs; `merge()` returns a new map with another map's costs over them. The
profile keeps a copy of its cost map, so build a new profile to pick up changes:

```php
// the mob's own costs win, the amphibious ones fill in the rest
$costMap = BlockPathTypeCostMap::amphibious()->merge($mobCostMap);
```

### Pre-made evaluators

| Evaluator | Use for |
| --- | --- |
| `NodeEvaluator` | Abstract base class — implement everything yourself. |
| `WalkNodeEvaluator` | Land mobs. 4 cardinal + 4 diagonal neighbors with up-step/jump and fall checks; handles fences, doors, rails and water borders. |
| `FlightNodeEvaluator` | Flying mobs. 6 cardinals + edges + corners; extra cost for flying close to the ground. |
| `AmphibiousNodeEvaluator` | Mobs that walk on land and also swim through water, like axolotls. Use with `BlockPathTypeCostMap::amphibious()` costs. |
| `SwimNodeEvaluator` | Mobs that can only move through water, like fish: land and air are blocked. Searches in 3D like the flight one. |

Everything a `MovementProfile` takes:

```php
$profile = new MovementProfile(
	width: 0.6,
	height: 1.95,
	maxUpStep: 0.6,
	maxFallDistance: 3,
	costMap: new BlockPathTypeCostMap(),
	canPassDoors: false,
	canOpenDoors: true,
	canFloat: true,
	canWalkOverFences: false,
	liquidsThatCanStandOn: [BlockTypeIds::LAVA],
	eyeHeight: 1.74             // defaults like PocketMine's EntitySizeInfo
);
```

### Path types

An evaluator asks a `PathTypeResolver` what the blocks around it are: open,
walkable, a fence, a closed door, water and so on (`BlockPathType`). Each
evaluator comes with the resolver of its way of moving (`WalkPathTypeResolver`,
`FlightPathTypeResolver`, `AmphibiousPathTypeResolver`, `SwimPathTypeResolver`) and takes another one in
its constructor.

- `getBlockPathType(BlockGetter, x, y, z)` — the type of one block.
- `getPathType(BlockGetter, MovementProfile, x, y, z, Vector3 $start)` — the type of
  all the blocks a mob of that profile takes, applying its door flags and costs, and what
  the search start changes (a rail is only passable from a rail).

`BlockClassifier` holds what does not depend on the way of moving: a block's own
type, its type for a mob on land (`getType()`), dangers around it and floor levels.
None of these need a search, so they work on the main thread with a
`WorldBlockGetter`:

```php
use IvanCraft623\Pathfinder\pathtype\BlockClassifier;
use IvanCraft623\Pathfinder\world\WorldBlockGetter;

$blockGetter = new WorldBlockGetter($world); // keep it, it is reusable
$type = BlockClassifier::getType($blockGetter, $x, $y, $z);
$floorY = BlockClassifier::getFloorLevel($blockGetter, $position);
```

### Building your own evaluator

Extend `NodeEvaluator`, pass its resolver to the parent constructor and implement:

- `getStart() : Node` — the start node, usually derived from `$this->context->start`.
- `getNeighbors(Node $node) : array` — which nodes are reachable from `$node`.

`getGoal()` defaults to the block of the target, override it if needed. You have
access to `$this->context` (the block getter, the profile, the start and whether
it is on the ground) and `$this->nodes` (the node cache), plus the `getNodeAt()`,
`getStartNode()`, `getCachedPathType()` and `getPathfindingMalus()` helpers. Costs
always come from the profile's cost map.

```php
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\Node;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\pathtype\FlightPathTypeResolver;
use pocketmine\math\Facing;

final class SimpleFlyingEvaluator extends NodeEvaluator {

	public function __construct(){
		parent::__construct(new FlightPathTypeResolver());
	}

	public function getStart() : Node{
		return $this->getStartNode($this->context->start);
	}

	/**
	 * @return Node[]
	 */
	public function getNeighbors(Node $node) : array{
		$neighbors = [];

		foreach (Facing::ALL as $side) {
			[$dx, $dy, $dz] = Facing::OFFSET[$side];
			$x = $node->x() + $dx;
			$y = $node->y() + $dy;
			$z = $node->z() + $dz;

			if (!$this->context->blockGetter->isInWorld($x, $y, $z)) {
				continue;
			}

			$type = $this->getCachedPathType($x, $y, $z);
			$malus = $this->getPathfindingMalus($type);
			if ($malus >= 0) {
				$neighbor = $this->getNodeAt($x, $y, $z);
				$neighbor->type = $type;
				$neighbor->costMalus = $malus;
				if (!$neighbor->closed) {
					$neighbors[] = $neighbor;
				}
			}
		}

		return $neighbors;
	}
}
```

## 🧭 Navigation

A minimal navigator that moves an entity along a path: it computes the path
asynchronously, advances waypoint by waypoint, and jumps when the next node is
out of reach. See
[`PathNavigation`](https://github.com/IvanCraft623/MobPlugin/blob/main/src/IvanCraft623/MobPlugin/entity/ai/navigation/PathNavigation.php)
and
[`MoveControl`](https://github.com/IvanCraft623/MobPlugin/blob/main/src/IvanCraft623/MobPlugin/entity/ai/control/MoveControl.php)
in MobPlugin for a full production implementation (stuck detection, corner
cutting, path trimming, timeouts).

```php
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathCursor;
use IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\execution\SearchExecutor;
use IvanCraft623\Pathfinder\world\Terrain;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use function sqrt;

final class Navigator {

	private ?PathCursor $cursor = null;

	public function __construct(
		private Entity $entity,
		private NodeEvaluator $evaluator,
		private SearchExecutor $executor,
		private float $speed = 0.4
	){}

	public function moveTo(Vector3 $target) : void{
		$size = $this->entity->getSize();
		$this->executor->search(
			$this->evaluator,
			new MovementProfile($size->getWidth(), $size->getHeight(), canOpenDoors: true),
			Terrain::fromWorld($this->entity->getWorld()),
			new SearchRequest(
				$this->entity->getPosition(),
				$target,
				maxVisitedNodes: 2000,
				maxDistanceFromStart: 32,
				onGround: $this->entity->isOnGround()
			)
		)->getPromise()->onCompletion(function(Path $path) : void{
			$this->cursor = new PathCursor($path);
		}, static function() : void{});
	}

	public function tick() : void{
		if ($this->cursor === null || $this->cursor->isDone()) {
			$this->entity->setMotion(Vector3::zero());

			return;
		}

		$pos = $this->entity->getPosition();
		$nodePos = $this->cursor->getNextNodePos();

		// Advance once the entity is close enough to the waypoint
		if ($pos->distanceSquared($nodePos->add(0.5, 0, 0.5)) < 1) {
			$this->cursor->advance();

			if ($this->cursor->isDone()) {
				return;
			}
			$nodePos = $this->cursor->getNextNodePos();
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
		return $this->cursor === null || $this->cursor->isDone();
	}

	public function stop() : void{
		$this->cursor = null;
	}
}
```

### Putting it together

```php
$executor = new AsyncSearchExecutor($server->getAsyncPool(), $plugin->getLogger());
$navigator = new Navigator($entity, new WalkNodeEvaluator(), $executor); // or FlightNodeEvaluator for flying mobs

$navigator->moveTo(new Vector3(100, 64, 100));

// then call $navigator->tick() every game tick while the mob should move
```
