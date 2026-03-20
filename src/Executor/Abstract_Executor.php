<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Executor;

use Doctrine\Common\Data_Fixtures\Fixture_Interface;
use Doctrine\Common\Data_Fixtures\Ordered_Fixture_Interface;
use Doctrine\Common\Data_Fixtures\Purger\Purger_Interface;
use Doctrine\Common\Data_Fixtures\Reference_Repository;
use Doctrine\Common\Data_Fixtures\Shared_Fixture_Interface;
use Doctrine\Persistence\Object_Manager;
use Exception;
use function get_debug_type;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use function sprintf;
/**
 * Abstract fixture executor.
 *
 * @internal since 1.8.0
 */
abstract class Abstract_Executor implements Logger_Aware_Interface
{
    use Logger_Aware_Trait;
    /**
     * Purger instance for purging database before loading data fixtures
     */
    protected Purger_Interface|null $purger = null;
    /**
     * Fixture reference repository
     */
    protected Reference_Repository $reference_repository;
    public function __construct(Object_Manager $manager)
    {
        $this->reference_repository = new Reference_Repository($manager);
    }
    public function get_reference_repository(): Reference_Repository
    {
        return $this->reference_repository;
    }
    public function set_reference_repository(Reference_Repository $reference_repository): void
    {
        $this->reference_repository = $reference_repository;
    }
    /**
     * Sets the Purger instance to use for this executor instance.
     */
    public function set_purger(Purger_Interface $purger): void
    {
        $this->purger = $purger;
    }
    public function get_purger(): Purger_Interface
    {
        return $this->purger;
    }
    /**
     * Load a fixture with the given persistence manager.
     */
    public function load(Object_Manager $manager, Fixture_Interface $fixture): void
    {
        if ($this->logger) {
            $prefix = '';
            if ($fixture instanceof Ordered_Fixture_Interface) {
                $prefix = sprintf('[%d] ', $fixture->get_order());
            }
            $this->logger->debug('loading ' . $prefix . get_debug_type($fixture));
        }
        // additionally pass the instance of reference repository to shared fixtures
        if ($fixture instanceof Shared_Fixture_Interface) {
            $fixture->set_reference_repository($this->reference_repository);
        }
        $fixture->load($manager);
        $manager->clear();
    }
    /**
     * Purges the database before loading.
     *
     * @throws Exception if the purger is not defined.
     */
    public function purge(): void
    {
        if ($this->purger === null) {
            throw new Exception(Purger_Interface::class . ' instance is required if you want to purge the database before loading your data fixtures.');
        }
        $this->logger?->debug('purging database');
        $this->purger->purge();
    }
    /**
     * Executes the given array of data fixtures.
     *
     * @param FixtureInterface[] $fixtures Array of fixtures to execute.
     * @param bool               $append   Whether to append the data fixtures or purge the database before loading.
     */
    abstract public function execute(array $fixtures, bool $append = false): void;
}