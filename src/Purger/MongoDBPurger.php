<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Purger;

use Doctrine\ODM\Mongo_Db\Document_Manager;
use function method_exists;
/**
 * Class responsible for purging databases of data before reloading data fixtures.
 */
final class Mongo_Db_Purger implements Mongo_Db_Purger_Interface
{
    private Mongo_Db_Purge_Mode $purge_mode = Mongo_Db_Purge_Mode::Drop;
    /**
     * Construct new purger instance.
     *
     * @param DocumentManager|null $dm DocumentManager instance used for persistence.
     */
    public function __construct(private Document_Manager|null $dm = null)
    {
    }
    /**
     * If the purge should be done through collection drop() or deleteMany()
     */
    public function set_purge_mode(Mongo_Db_Purge_Mode $mode): void
    {
        $this->purge_mode = $mode;
    }
    public function get_purge_mode(): Mongo_Db_Purge_Mode
    {
        return $this->purge_mode;
    }
    /**
     * Set the DocumentManager instance this purger instance should use.
     */
    public function set_document_manager(Document_Manager $dm): void
    {
        $this->dm = $dm;
    }
    /**
     * Retrieve the DocumentManager instance this purger instance is using.
     */
    public function get_object_manager(): Document_Manager
    {
        return $this->dm;
    }
    public function purge(): void
    {
        match ($this->purge_mode) {
            Mongo_Db_Purge_Mode::Delete => $this->purge_with_delete(),
            Mongo_Db_Purge_Mode::Drop => $this->purge_with_drop(),
        };
    }
    private function purge_with_delete(): void
    {
        $all_metadata = $this->dm->get_metadata_factory()->get_all_metadata();
        foreach ($all_metadata as $metadata) {
            if ($metadata->is_mapped_superclass) {
                continue;
            }
            $this->dm->get_document_collection($metadata->name)->delete_many([]);
        }
    }
    private function purge_with_drop(): void
    {
        $all_metadata = $this->dm->get_metadata_factory()->get_all_metadata();
        foreach ($all_metadata as $metadata) {
            if ($metadata->is_mapped_superclass) {
                continue;
            }
            $this->dm->get_document_collection($metadata->name)->drop();
        }
        $schema_manager = $this->dm->get_schema_manager();
        $schema_manager->create_collections();
        $schema_manager->ensure_indexes();
        // Requires doctrine/mongodb-odm 2.8
        // @phpstan-ignore function.alreadyNarrowedType
        method_exists($schema_manager, 'createSearchIndexes') && $schema_manager->create_search_indexes();
    }
}