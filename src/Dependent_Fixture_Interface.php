<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

/**
 * DependentFixtureInterface needs to be implemented by fixtures which depend on other fixtures
 */
interface Dependent_Fixture_Interface
{
    /**
     * This method must return an array of fixtures classes
     * on which the implementing class depends on
     *
     * @phpstan-return array<class-string<FixtureInterface>>
     */
    public function get_dependencies(): array;
}