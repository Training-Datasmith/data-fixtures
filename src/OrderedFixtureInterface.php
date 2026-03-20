<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

/**
 * Ordered Fixture interface needs to be implemented
 * by fixtures, which needs to have a specific order
 * when being loaded by directory scan for example
 */
interface Ordered_Fixture_Interface
{
    /**
     * Get the order of this fixture
     */
    public function get_order(): int;
}