<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

use function array_keys;
use function array_merge;
use ArrayIterator;
use function asort;
use function class_exists;
use function class_implements;
use function count;
use Doctrine\Common\Data_Fixtures\Exception\Circular_Reference_Exception;
use function get_declared_classes;
use function implode;
use function in_array;
use InvalidArgumentException;
use function is_dir;
use function is_readable;
use Iterator;
use function realpath;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
use ReflectionClass;
use RuntimeException;
use function sort;
use Spl_File_Info;
use function sprintf;
use function usort;
/**
 * Class responsible for loading data fixture classes.
 */
class Loader
{
    /**
     * Array of fixture object instances to execute.
     *
     * @phpstan-var array<class-string<FixtureInterface>, FixtureInterface>
     */
    private array $fixtures = [];
    /**
     * Array of ordered fixture object instances.
     *
     * @phpstan-var array<class-string<FixtureInterface>|int, FixtureInterface>
     */
    private array $ordered_fixtures = [];
    /**
     * Determines if we must order fixtures by number
     */
    private bool $order_fixtures_by_number = false;
    /**
     * Determines if we must order fixtures by its dependencies
     */
    private bool $order_fixtures_by_dependencies = false;
    /**
     * The file extension of fixture files.
     */
    private string $file_extension = '.php';
    /**
     * Find fixtures classes in a given directory and load them.
     *
     * @param string $dir Directory to find fixture classes in.
     *
     * @return array $fixtures Array of loaded fixture object instances.
     */
    public function load_from_directory(string $dir): array
    {
        if (!is_dir($dir)) {
            throw new InvalidArgumentException(sprintf('"%s" does not exist', $dir));
        }
        $iterator = new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($dir), Recursive_Iterator_Iterator::LEAVES_ONLY);
        return $this->load_from_iterator($iterator);
    }
    /**
     * Find fixtures classes in a given file and load them.
     *
     * @param string $fileName File to find fixture classes in.
     *
     * @return array $fixtures Array of loaded fixture object instances.
     */
    public function load_from_file(string $file_name): array
    {
        if (!is_readable($file_name)) {
            throw new InvalidArgumentException(sprintf('"%s" does not exist or is not readable', $file_name));
        }
        $iterator = new ArrayIterator([new Spl_File_Info($file_name)]);
        return $this->load_from_iterator($iterator);
    }
    /**
     * Has fixture?
     */
    public function has_fixture(Fixture_Interface $fixture): bool
    {
        return isset($this->fixtures[$fixture::class]);
    }
    /**
     * Get a specific fixture instance
     */
    public function get_fixture(string $class_name): Fixture_Interface
    {
        if (!isset($this->fixtures[$class_name])) {
            throw new InvalidArgumentException(sprintf('"%s" is not a registered fixture', $class_name));
        }
        return $this->fixtures[$class_name];
    }
    /**
     * Add a fixture object instance to the loader.
     */
    public function add_fixture(Fixture_Interface $fixture): void
    {
        $fixture_class = $fixture::class;
        if (isset($this->fixtures[$fixture_class])) {
            return;
        }
        if ($fixture instanceof Ordered_Fixture_Interface && $fixture instanceof Dependent_Fixture_Interface) {
            throw new InvalidArgumentException(sprintf('Class "%s" can\'t implement "%s" and "%s" at the same time.', $fixture::class, 'OrderedFixtureInterface', 'DependentFixtureInterface'));
        }
        $this->fixtures[$fixture_class] = $fixture;
        if ($fixture instanceof Ordered_Fixture_Interface) {
            $this->order_fixtures_by_number = true;
        } elseif ($fixture instanceof Dependent_Fixture_Interface) {
            $this->order_fixtures_by_dependencies = true;
            foreach ($fixture->get_dependencies() as $class) {
                if (!class_exists($class)) {
                    continue;
                }
                $this->add_fixture($this->create_fixture($class));
            }
        }
    }
    /**
     * Returns the array of data fixtures to execute.
     *
     * @phpstan-return array<class-string<FixtureInterface>|int, FixtureInterface>
     */
    public function get_fixtures(): array
    {
        $this->ordered_fixtures = [];
        if ($this->order_fixtures_by_number) {
            $this->order_fixtures_by_number();
        }
        if ($this->order_fixtures_by_dependencies) {
            $this->order_fixtures_by_dependencies();
        }
        if (!$this->order_fixtures_by_number && !$this->order_fixtures_by_dependencies) {
            $this->ordered_fixtures = $this->fixtures;
        }
        return $this->ordered_fixtures;
    }
    /**
     * Check if a given fixture is transient and should not be considered a data fixtures
     * class.
     *
     * @phpstan-param class-string<object> $className
     */
    public function is_transient(string $class_name): bool
    {
        $rc = new ReflectionClass($class_name);
        if ($rc->is_abstract()) {
            return true;
        }
        $interfaces = class_implements($class_name);
        return !in_array(Fixture_Interface::class, $interfaces);
    }
    /**
     * Creates the fixture object from the class.
     */
    protected function create_fixture(string $class): Fixture_Interface
    {
        return new $class();
    }
    /**
     * Orders fixtures by number
     *
     * @todo maybe there is a better way to handle reordering
     */
    private function order_fixtures_by_number(): void
    {
        $this->ordered_fixtures = $this->fixtures;
        usort($this->ordered_fixtures, static function (Fixture_Interface $a, Fixture_Interface $b): int {
            if ($a instanceof Ordered_Fixture_Interface && $b instanceof Ordered_Fixture_Interface) {
                if ($a->get_order() === $b->get_order()) {
                    return 0;
                }
                return $a->get_order() < $b->get_order() ? -1 : 1;
            }
            if ($a instanceof Ordered_Fixture_Interface) {
                return $a->get_order() === 0 ? 0 : 1;
            }
            if ($b instanceof Ordered_Fixture_Interface) {
                return $b->get_order() === 0 ? 0 : -1;
            }
            return 0;
        });
    }
    /**
     * Orders fixtures by dependencies
     */
    private function order_fixtures_by_dependencies(): void
    {
        /** @phpstan-var array<class-string<DependentFixtureInterface>, int> */
        $sequence_for_classes = [];
        // If fixtures were already ordered by number then we need
        // to remove classes which are not instances of OrderedFixtureInterface
        // in case fixtures implementing DependentFixtureInterface exist.
        // This is because, in that case, the method orderFixturesByDependencies
        // will handle all fixtures which are not instances of
        // OrderedFixtureInterface
        if ($this->order_fixtures_by_number) {
            $count = count($this->ordered_fixtures);
            for ($i = 0; $i < $count; ++$i) {
                if ($this->ordered_fixtures[$i] instanceof Ordered_Fixture_Interface) {
                    continue;
                }
                unset($this->ordered_fixtures[$i]);
            }
        }
        // First we determine which classes has dependencies and which don't
        foreach ($this->fixtures as $fixture) {
            $fixture_class = $fixture::class;
            if ($fixture instanceof Ordered_Fixture_Interface) {
                continue;
            }
            if ($fixture instanceof Dependent_Fixture_Interface) {
                $dependencies_classes = $fixture->get_dependencies();
                $this->validate_dependencies($dependencies_classes);
                if (empty($dependencies_classes)) {
                    throw new InvalidArgumentException(sprintf('Method "%s" in class "%s" must return an array of classes which are dependencies for the fixture, and it must be NOT empty.', 'getDependencies', $fixture_class));
                }
                if (in_array($fixture_class, $dependencies_classes)) {
                    throw new InvalidArgumentException(sprintf('Class "%s" can\'t have itself as a dependency', $fixture_class));
                }
                // We mark this class as unsequenced
                $sequence_for_classes[$fixture_class] = -1;
            } else {
                // This class has no dependencies, so we assign 0
                $sequence_for_classes[$fixture_class] = 0;
            }
        }
        // Now we order fixtures by sequence
        $sequence = 1;
        $last_count = -1;
        while (($count = count($unsequenced_classes = $this->get_unsequenced_classes($sequence_for_classes))) > 0 && $count !== $last_count) {
            foreach ($unsequenced_classes as $class) {
                $fixture = $this->fixtures[$class];
                $dependencies = $fixture->get_dependencies();
                $unsequenced_dependencies = $this->get_unsequenced_classes($sequence_for_classes, $dependencies);
                if (count($unsequenced_dependencies) !== 0) {
                    continue;
                }
                $sequence_for_classes[$class] = $sequence++;
            }
            $last_count = $count;
        }
        $ordered_fixtures = [];
        // If there're fixtures unsequenced left and they couldn't be sequenced,
        // it means we have a circular reference
        if ($count > 0) {
            $msg = 'Classes "%s" have produced a CircularReferenceException. ';
            $msg .= 'An example of this problem would be the following: Class C has class B as its dependency. ';
            $msg .= 'Then, class B has class A has its dependency. Finally, class A has class C as its dependency. ';
            $msg .= 'This case would produce a CircularReferenceException.';
            throw new Circular_Reference_Exception(sprintf($msg, implode(',', $unsequenced_classes)));
        }
        // We order the classes by sequence
        asort($sequence_for_classes);
        foreach ($sequence_for_classes as $class => $sequence) {
            // If fixtures were ordered
            $ordered_fixtures[] = $this->fixtures[$class];
        }
        $this->ordered_fixtures = array_merge($this->ordered_fixtures, $ordered_fixtures);
    }
    /** @phpstan-param iterable<class-string> $dependenciesClasses */
    private function validate_dependencies(iterable $dependencies_classes): bool
    {
        $loaded_fixture_classes = array_keys($this->fixtures);
        foreach ($dependencies_classes as $class) {
            if (!in_array($class, $loaded_fixture_classes)) {
                throw new RuntimeException(sprintf('Fixture "%s" was declared as a dependency, but it should be added in fixture loader first.', $class));
            }
        }
        return true;
    }
    /**
     * @phpstan-param array<class-string<DependentFixtureInterface>, int> $sequences
     * @phpstan-param iterable<class-string<FixtureInterface>>|null       $classes
     *
     * @phpstan-return array<class-string<FixtureInterface>>
     */
    private function get_unsequenced_classes(array $sequences, iterable|null $classes = null): array
    {
        $unsequenced_classes = [];
        if ($classes === null) {
            $classes = array_keys($sequences);
        }
        foreach ($classes as $class) {
            if (!isset($sequences[$class])) {
                continue;
            }
            if ($sequences[$class] !== -1) {
                continue;
            }
            $unsequenced_classes[] = $class;
        }
        return $unsequenced_classes;
    }
    /**
     * Load fixtures from files contained in iterator.
     *
     * @phpstan-param Iterator<SplFileInfo> $iterator Iterator over files from
     *                                              which fixtures should be loaded.
     *
     * @phpstan-return list<FixtureInterface> $fixtures Array of loaded fixture object instances.
     */
    private function load_from_iterator(Iterator $iterator): array
    {
        $included_files = [];
        foreach ($iterator as $file) {
            $file_name = $file->get_basename($this->file_extension);
            if ($file_name === $file->get_basename()) {
                continue;
            }
            $source_file = realpath($file->get_path_name());
            if ($source_file === false) {
                continue;
            }
            require_once $source_file;
            $included_files[] = $source_file;
        }
        $fixtures = [];
        $declared = get_declared_classes();
        // Make the declared classes order deterministic
        sort($declared);
        foreach ($declared as $class_name) {
            $refl_class = new ReflectionClass($class_name);
            $source_file = $refl_class->get_file_name();
            if (!in_array($source_file, $included_files)) {
                continue;
            }
            if ($this->is_transient($class_name)) {
                continue;
            }
            $fixture = $this->create_fixture($class_name);
            $fixtures[] = $fixture;
            $this->add_fixture($fixture);
        }
        return $fixtures;
    }
}