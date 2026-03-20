<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

use function array_key_exists;
use function array_keys;
use function array_map;
use BadMethodCallException;
use Doctrine\ODM\PHPCR\Document_Manager as PhpcrDocumentManager;
use Doctrine\ORM\Unit_Of_Work as OrmUnitOfWork;
use Doctrine\Persistence\Object_Manager;
use OutOfBoundsException;
use function sprintf;
/**
 * ReferenceRepository class manages references for
 * fixtures in order to easily support the relations
 * between fixtures
 */
class Reference_Repository
{
    /**
     * List of named references to the fixture objects
     * gathered during fixure loading
     *
     * @phpstan-var array<class-string, array<string|int, object>>
     */
    private array $references_by_class = [];
    /**
     * List of identifiers stored for references
     * in case a reference gets no longer managed, it will
     * use a proxy referenced by this identity
     *
     * @phpstan-var array<class-string, array<string, mixed>>
     */
    private array $identities_by_class = [];
    /**
     * Currently used object manager
     */
    private readonly Object_Manager $manager;
    public function __construct(Object_Manager $manager)
    {
        $this->manager = $manager;
    }
    /**
     * Get identifier for a unit of work
     *
     * @param object $reference Reference object
     * @param object $uow       Unit of work
     */
    protected function get_identifier(object $reference, object $uow): mixed
    {
        // In case Reference is not yet managed in UnitOfWork
        if (!$this->has_identifier($reference)) {
            $class = $this->manager->get_class_metadata($reference::class);
            return $class->get_identifier_values($reference);
        }
        // Dealing with ORM UnitOfWork
        if ($uow instanceof Orm_Unit_Of_Work) {
            return $uow->get_entity_identifier($reference);
        }
        // PHPCR ODM UnitOfWork
        if ($this->manager instanceof Phpcr_Document_Manager) {
            return $uow->get_document_id($reference);
        }
        // ODM UnitOfWork
        return $uow->get_document_identifier($reference);
    }
    /**
     * Set the reference entry identified by $name
     * and referenced to $reference. If $name
     * already is set, it overrides it
     */
    public function set_reference(string $name, object $reference): void
    {
        $class = $this->get_real_class($reference::class);
        $this->references_by_class[$class][$name] = $reference;
        if (!$this->has_identifier($reference)) {
            return;
        }
        // in case if reference is set after flush, store its identity
        $uow = $this->manager->get_unit_of_work();
        $identifier = $this->get_identifier($reference, $uow);
        $this->identities_by_class[$class][$name] = $identifier;
    }
    /**
     * Store the identifier of a reference
     *
     * @param class-string $class
     */
    public function set_reference_identity(string $name, mixed $identity, string $class): void
    {
        $this->identities_by_class[$class][$name] = $identity;
    }
    /**
     * Set the reference entry identified by $name
     * and referenced to managed $object. $name must
     * not be set yet
     *
     * Notice: in case if identifier is generated after
     * the record is inserted, be sure to use this method
     * after $object is flushed
     *
     * @param object $object - managed object
     *
     * @throws BadMethodCallException - if repository already has a reference by $name.
     */
    public function add_reference(string $name, object $object): void
    {
        $class = $this->get_real_class($object::class);
        if (isset($this->references_by_class[$class][$name])) {
            throw new BadMethodCallException(sprintf('Reference to "%s" for class "%s" already exists, use method setReference() in order to override it', $name, $class));
        }
        $this->set_reference($name, $object);
    }
    /**
     * Loads an object using stored reference
     * named by $name
     *
     * @phpstan-param class-string<T> $class
     *
     * @phpstan-return T
     *
     * @throws OutOfBoundsException - if repository does not exist.
     *
     * @template T of object
     */
    public function get_reference(string $name, string $class): object
    {
        if (!$this->has_reference($name, $class)) {
            throw new OutOfBoundsException(sprintf('Reference to "%s" for class "%s" does not exist', $name, $class));
        }
        $reference = $this->references_by_class[$class][$name];
        $identity = $this->identities_by_class[$class][$name] ?? null;
        $meta = $this->manager->get_class_metadata($class);
        if (!$this->manager->contains($reference) && $identity !== null) {
            $reference = $this->manager->get_reference($meta->name, $identity);
            $this->references_by_class[$class][$name] = $reference;
            // already in identity map
        }
        return $reference;
    }
    /**
     * Check if an object is stored using reference
     * named by $name
     *
     * @phpstan-param class-string $class
     */
    public function has_reference(string $name, string $class): bool
    {
        return isset($this->references_by_class[$class][$name]);
    }
    /**
     * Searches for reference names in the
     * list of stored references
     *
     * @return array<string>
     */
    public function get_reference_names(object $reference): array
    {
        $class = $this->get_real_class($reference::class);
        if (!isset($this->references_by_class[$class])) {
            return [];
        }
        return array_map(strval(...), array_keys($this->references_by_class[$class], $reference, true));
    }
    /**
     * Checks if reference has identity stored
     *
     * @param class-string $class
     */
    public function has_identity(string $name, string $class): bool
    {
        return array_key_exists($class, $this->identities_by_class) && array_key_exists($name, $this->identities_by_class[$class]);
    }
    /**
     * Get all stored identities
     *
     * @phpstan-return array<class-string, array<string, mixed>>
     */
    public function get_identities_by_class(): array
    {
        return $this->identities_by_class;
    }
    /**
     * Get all stored references
     *
     * @phpstan-return array<class-string, array<string|int, object>>
     */
    public function get_references_by_class(): array
    {
        return $this->references_by_class;
    }
    /**
     * Get object manager
     */
    public function get_manager(): Object_Manager
    {
        return $this->manager;
    }
    /**
     * Get real class name of a reference that could be a proxy
     *
     * @param string $className Class name of reference object
     *
     * @return class-string
     */
    protected function get_real_class(string $class_name): string
    {
        return $this->manager->get_class_metadata($class_name)->get_name();
    }
    /**
     * Checks if object has identifier already in unit of work.
     */
    private function has_identifier(object $reference): bool
    {
        // in case if reference is set after flush, store its identity
        $uow = $this->manager->get_unit_of_work();
        if ($this->manager instanceof Phpcr_Document_Manager) {
            return $uow->contains($reference);
        }
        return $uow->is_in_identity_map($reference);
    }
}