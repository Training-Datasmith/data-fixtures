<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

use Doctrine\Persistence\Object_Manager;
/**
 * Interface contract for fixture classes to implement.
 */
interface Fixture_Interface
{
    /**
     * Load data fixtures with the passed EntityManager
     */
    public function load(Object_Manager $manager): void;
}