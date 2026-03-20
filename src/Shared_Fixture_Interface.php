<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

/**
 * Shared Fixture interface needs to be implemented
 * by fixtures, which needs some references to be shared
 * among other fixture classes in order to maintain
 * relation mapping
 */
interface Shared_Fixture_Interface extends Fixture_Interface
{
    public function set_reference_repository(Reference_Repository $reference_repository): void;
}