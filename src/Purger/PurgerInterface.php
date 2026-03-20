<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Purger;

/**
 * PurgerInterface
 */
interface Purger_Interface
{
    /**
     * Purge the data from the database for the given EntityManager.
     */
    public function purge(): void;
}