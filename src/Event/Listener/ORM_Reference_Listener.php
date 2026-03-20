<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Event\Listener;

use Doctrine\Common\Data_Fixtures\Reference_Repository;
use Doctrine\Common\Event_Subscriber;
use Doctrine\ORM\Event\Post_Persist_Event_Args;
/**
 * Reference Listener populates identities for
 * stored references
 */
final class Orm_Reference_Listener implements Event_Subscriber
{
    public function __construct(private readonly Reference_Repository $reference_repository)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function get_subscribed_events(): array
    {
        // would be better to use onClear, but it is supported only in 2.1
        return ['postPersist'];
    }
    /**
     * Populates identities for stored references
     */
    public function post_persist(Post_Persist_Event_Args $args): void
    {
        $object = $args->get_object();
        $names = $this->reference_repository->get_reference_names($object);
        if ($names === false) {
            return;
        }
        foreach ($names as $name) {
            $identity = $args->get_object_manager()->get_unit_of_work()->get_entity_identifier($object);
            $this->reference_repository->set_reference_identity($name, $identity, $object::class);
        }
    }
}