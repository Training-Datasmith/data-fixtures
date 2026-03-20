<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Executor;

use Doctrine\Common\Data_Fixtures\Event\Listener\Mongo_Db_Reference_Listener;
use Doctrine\Common\Data_Fixtures\Purger\Mongo_Db_Purger_Interface;
use Doctrine\Common\Data_Fixtures\Reference_Repository;
use Doctrine\ODM\Mongo_Db\Document_Manager;
/**
 * Class responsible for executing data fixtures.
 */
final class Mongo_Db_Executor extends Abstract_Executor
{
    private Mongo_Db_Reference_Listener $listener;
    /**
     * Construct new fixtures loader instance.
     *
     * @param DocumentManager $dm DocumentManager instance used for persistence.
     */
    public function __construct(private readonly Document_Manager $dm, Mongo_Db_Purger_Interface|null $purger = null)
    {
        if ($purger !== null) {
            $this->purger = $purger;
            $this->purger->set_document_manager($dm);
        }
        parent::__construct($dm);
        $this->listener = new Mongo_Db_Reference_Listener($this->reference_repository);
        $dm->get_event_manager()->add_event_subscriber($this->listener);
    }
    /**
     * Retrieve the DocumentManager instance this executor instance is using.
     */
    public function get_object_manager(): Document_Manager
    {
        return $this->dm;
    }
    public function set_reference_repository(Reference_Repository $reference_repository): void
    {
        $this->dm->get_event_manager()->remove_event_listener($this->listener->get_subscribed_events(), $this->listener);
        $this->reference_repository = $reference_repository;
        $this->listener = new Mongo_Db_Reference_Listener($this->reference_repository);
        $this->dm->get_event_manager()->add_event_subscriber($this->listener);
    }
    /** @inheritDoc */
    public function execute(array $fixtures, bool $append = false): void
    {
        if ($append === false) {
            $this->purge();
        }
        foreach ($fixtures as $fixture) {
            $this->load($this->dm, $fixture);
        }
    }
}