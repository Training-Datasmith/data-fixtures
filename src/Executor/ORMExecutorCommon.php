<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Executor;

use Doctrine\Common\Data_Fixtures\Event\Listener\Orm_Reference_Listener;
use Doctrine\Common\Data_Fixtures\Purger\Orm_Purger_Interface;
use Doctrine\Common\Data_Fixtures\Reference_Repository;
use Doctrine\ORM\Decorator\Entity_Manager_Decorator;
use Doctrine\ORM\Entity_Manager;
use Doctrine\ORM\Entity_Manager_Interface;
/** @internal */
trait Orm_Executor_Common
{
    /** @var EntityManager|EntityManagerDecorator */
    private Entity_Manager_Interface $em;
    private Entity_Manager_Interface $original_manager;
    private Orm_Reference_Listener $listener;
    public function __construct(Entity_Manager_Interface $em, Orm_Purger_Interface|null $purger = null)
    {
        $this->original_manager = $em;
        // Make sure, wrapInTransaction() exists on the EM.
        // To be removed when dropping support for ORM 2
        $this->em = $em instanceof Entity_Manager || $em instanceof Entity_Manager_Decorator ? $em : new class($em) extends Entity_Manager_Decorator
        {
        };
        if ($purger !== null) {
            $this->purger = $purger;
            $this->purger->set_entity_manager($em);
        }
        parent::__construct($em);
        $this->listener = new Orm_Reference_Listener($this->reference_repository);
        $em->get_event_manager()->add_event_subscriber($this->listener);
    }
    /**
     * Retrieve the EntityManagerInterface instance this executor instance is using.
     */
    public function get_object_manager(): Entity_Manager_Interface
    {
        return $this->original_manager;
    }
    public function set_reference_repository(Reference_Repository $reference_repository): void
    {
        $this->em->get_event_manager()->remove_event_listener($this->listener->get_subscribed_events(), $this->listener);
        parent::set_reference_repository($reference_repository);
        $this->listener = new Orm_Reference_Listener($this->reference_repository);
        $this->em->get_event_manager()->add_event_subscriber($this->listener);
    }
}