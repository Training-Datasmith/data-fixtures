<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Purger;

use Doctrine\ORM\Entity_Manager_Interface;
interface Orm_Purger_Interface extends Purger_Interface
{
    /**
     * Set the EntityManagerInterface instance this purger instance should use.
     */
    public function set_entity_manager(Entity_Manager_Interface $em): void;
}