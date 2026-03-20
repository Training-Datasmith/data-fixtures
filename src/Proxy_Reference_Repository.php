<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function serialize;
use function unserialize;
/**
 * Proxy reference repository
 *
 * Allow data fixture references and identities to be persisted when cached data fixtures
 * are pre-loaded, for example, by LiipFunctionalTestBundle\Test\WebTestCase loadFixtures().
 */
class Proxy_Reference_Repository extends Reference_Repository
{
    /**
     * Serialize reference repository
     */
    public function serialize(): string
    {
        return serialize(['identitiesByClass' => $this->get_identities_by_class()]);
    }
    /**
     * Unserialize reference repository
     *
     * @param string $serializedData Serialized data
     */
    public function unserialize(string $serialized_data): void
    {
        $repository_data = unserialize($serialized_data);
        foreach ($repository_data['identitiesByClass'] as $class_name => $identities) {
            foreach ($identities as $name => $identity) {
                $this->set_reference($name, $this->get_manager()->get_reference($class_name, $identity));
                $this->set_reference_identity($name, $identity, $class_name);
            }
        }
    }
    /**
     * Load data fixture reference repository
     *
     * @param string $baseCacheName Base cache name
     */
    public function load(string $base_cache_name): bool
    {
        $filename = $base_cache_name . '.ser';
        if (!file_exists($filename)) {
            return false;
        }
        $serialized_data = file_get_contents($filename);
        if ($serialized_data === false) {
            return false;
        }
        $this->unserialize($serialized_data);
        return true;
    }
    /**
     * Save data fixture reference repository
     *
     * @param string $baseCacheName Base cache name
     */
    public function save(string $base_cache_name): void
    {
        $serialized_data = $this->serialize();
        file_put_contents($base_cache_name . '.ser', $serialized_data);
    }
}