<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

use function assert;
use BadMethodCallException;
/**
 * Abstract Fixture class helps to manage references
 * between fixture classes in order to set relations
 * among other fixtures
 */
abstract class Abstract_Fixture implements Shared_Fixture_Interface
{
    /**
     * Fixture reference repository
     */
    protected Reference_Repository|null $reference_repository = null;
    public function set_reference_repository(Reference_Repository $reference_repository): void
    {
        $this->reference_repository = $reference_repository;
    }
    private function get_reference_repository(): Reference_Repository
    {
        assert($this->reference_repository !== null);
        return $this->reference_repository;
    }
    /**
     * Set the reference entry identified by $name
     * and referenced to managed $object. If $name
     * already is set, it overrides it
     *
     * @see ReferenceRepository::setReference()
     *
     * @param object $object - managed object
     */
    public function set_reference(string $name, object $object): void
    {
        $this->get_reference_repository()->set_reference($name, $object);
    }
    /**
     * Set the reference entry identified by $name
     * and referenced to managed $object. If $name
     * already is set, it throws a
     * BadMethodCallException exception
     *
     * @see ReferenceRepository::addReference()
     *
     * @param object $object - managed object
     *
     * @throws BadMethodCallException - if repository already has a reference by $name.
     */
    public function add_reference(string $name, object $object): void
    {
        $this->get_reference_repository()->add_reference($name, $object);
    }
    /**
     * Loads an object using stored reference
     * named by $name
     *
     * @see ReferenceRepository::getReference()
     *
     * @phpstan-param class-string<T> $class
     *
     * @phpstan-return T
     *
     * @template T of object
     */
    public function get_reference(string $name, string $class): object
    {
        return $this->get_reference_repository()->get_reference($name, $class);
    }
    /**
     * Check if an object is stored using reference
     * named by $name
     *
     * @see ReferenceRepository::hasReference()
     *
     * @phpstan-param class-string $class
     */
    public function has_reference(string $name, string $class): bool
    {
        return $this->get_reference_repository()->has_reference($name, $class);
    }
}