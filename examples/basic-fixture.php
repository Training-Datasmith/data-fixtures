<?php

declare(strict_types=1);

/**
 * Example: Writing and loading Doctrine DataFixtures.
 *
 * This shows the three common patterns:
 *   1. A simple fixture (no ordering)
 *   2. An ordered fixture (numeric order)
 *   3. A dependent fixture (declares dependency on another fixture class)
 */

use Doctrine\Common\DataFixtures\Abstract_Fixture;
use Doctrine\Common\DataFixtures\Dependent_Fixture_Interface;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Ordered_Fixture_Interface;
use Doctrine\Persistence\ObjectManager;

// --- 1. Simple fixture ---

class Role_Fixture extends Abstract_Fixture implements Ordered_Fixture_Interface
{
    public function load(ObjectManager $manager): void
    {
        $role = new \App\Entity\Role();
        $role->setName('ROLE_USER');
        $manager->persist($role);
        $manager->flush();

        // Store reference for use by other fixtures
        $this->addReference('role-user', $role);
    }

    public function getOrder(): int
    {
        return 1; // runs first
    }
}

// --- 2. Dependent fixture ---

class User_Fixture extends Abstract_Fixture implements Dependent_Fixture_Interface
{
    public function load(ObjectManager $manager): void
    {
        $user = new \App\Entity\User();
        $user->setEmail('alice@example.com');
        // Retrieve the role persisted by Role_Fixture
        $user->addRole($this->getReference('role-user'));
        $manager->persist($user);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [Role_Fixture::class]; // Role_Fixture runs before this
    }
}

// --- 3. Loading fixtures via the Loader ---

$loader = new Loader();
$loader->addFixture(new Role_Fixture());
$loader->addFixture(new User_Fixture());

// $executor = new \Doctrine\Common\DataFixtures\Executor\ORM_Executor($entityManager, $purger);
// $executor->execute($loader->getFixtures());

echo 'Fixtures loaded: ' . count($loader->getFixtures()) . PHP_EOL;
foreach ($loader->getFixtures() as $fixture) {
    echo '  - ' . get_class($fixture) . PHP_EOL;
}
