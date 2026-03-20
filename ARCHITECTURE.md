# Architecture: data-fixtures

## Purpose

A Doctrine library for loading test/seed data into databases and document stores. It provides a standardised way to write reusable fixture classes, manage execution order (including dependency cycles detection), and purge/reload data between test runs.

## Directory Structure

```
src/
  Fixture_Interface.php               — Base contract every fixture must implement (load(ObjectManager))
  Abstract_Fixture.php                — Convenience base class; implements reference storage helpers
  Dependent_Fixture_Interface.php     — Optional: declare ordering dependencies between fixtures
  Ordered_Fixture_Interface.php       — Optional: declare a numeric order for fixture loading
  Shared_Fixture_Interface.php        — Marker: fixture is loaded once and shared across executors
  Loader.php                          — Discovers and instantiates fixture classes from files/directories
  Reference_Repository.php            — Stores named references to persisted objects for cross-fixture linking
  Proxy_Reference_Repository.php      — Lazy-loading proxy around Reference_Repository

  Executor/
    Abstract_Executor.php             — Base executor: orchestrates purge → load → flush lifecycle
    ORM_Executor.php                  — Doctrine ORM executor (EntityManager)
    Dry_Run_ORM_Executor.php          — Wraps everything in a rolled-back transaction
    Multiple_Transaction_ORM_Executor — Commits each fixture in its own transaction
    Mongo_DB_Executor.php             — MongoDB ODM executor
    PHPCR_Executor.php                — PHPCR executor

  Purger/
    ORM_Purger.php                    — Truncates or deletes all ORM-managed tables
    Mongo_DB_Purger.php               — Drops/empties MongoDB collections
    PHPCR_Purger.php                  — Removes PHPCR nodes

  Sorter/
    Topological_Sorter.php            — Kahn's algorithm for dependency-ordered fixture execution
    Vertex.php                        — Graph node used by Topological_Sorter

  Event/Listener/                     — Doctrine event listeners for post-persist reference tracking
  Exception/
    Circular_Reference_Exception.php  — Thrown when fixture dependencies form a cycle
```

## Key Design Decisions

- **Three ordering strategies** — fixtures can declare `get_order()` (numeric), `get_dependencies()` (named classes), or neither (unordered). The loader resolves all three into a single execution sequence.
- **Topological sort for dependencies** — `Topological_Sorter` runs Kahn's algorithm and throws `Circular_Reference_Exception` on cycles, preventing silent deadlocks.
- **Reference repository** — fixtures call `$this->add_reference('my-user', $user)` after persisting; subsequent fixtures retrieve it with `$this->get_reference('my-user')`, enabling relational data without hard-coding IDs.
- **Purge modes** — `ORM_Purger` supports both `DELETE` and `TRUNCATE` modes; TRUNCATE is faster but may fail on foreign-key-constrained tables.
- **Dry-run support** — `Dry_Run_ORM_Executor` wraps all loads in a savepoint-based transaction that is rolled back, useful for verifying fixture correctness without persisting data.

## Extension Points

- Implement `Fixture_Interface` (or extend `Abstract_Fixture`) to write custom fixture classes.
- Implement `Dependent_Fixture_Interface` to declare ordering dependencies between fixture classes.
- Implement `Purger_Interface` to support a new storage backend.
- Extend `Abstract_Executor` to add a new persistence layer.

## Dependency Flow

```
Loader::load_from_directory(path)
  └── discovers Fixture_Interface implementations

Abstract_Executor::execute(fixtures, append=false)
  ├── Purger_Interface::purge()          [when append=false]
  └── Topological_Sorter::sort(fixtures)
        └── for each fixture (ordered):
              fixture->load(ObjectManager)
                └── add_reference() → Reference_Repository
```
