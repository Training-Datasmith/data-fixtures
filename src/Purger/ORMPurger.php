<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Purger;

use function array_map;
use function array_reverse;
use function class_exists;
use function count;
use Doctrine\Common\Data_Fixtures\Sorter\Topological_Sorter;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Schema\Abstract_Named_Object;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Mapping\Many_To_Many_Owning_Side_Mapping;
use function in_array;
/**
 * Class responsible for purging databases of data before reloading data fixtures.
 */
final class Orm_Purger implements Orm_Purger_Interface
{
    public const PURGE_MODE_DELETE = 1;
    public const PURGE_MODE_TRUNCATE = 2;
    /**
     * If the purge should be done through DELETE or TRUNCATE statements
     */
    private int $purge_mode = self::PURGE_MODE_DELETE;
    /** @var list<string>|null */
    private array|null $cached_sql_statements = null;
    /**
     * Construct new purger instance.
     *
     * @param EntityManagerInterface|null $em       EntityManagerInterface instance used for persistence.
     * @param string[]                    $excluded array of table/view names to be excluded from purge
     */
    public function __construct(
        private Entity_Manager_Interface|null $em = null,
        /**
         * Table/view names to be excluded from purge
         */
        private readonly array $excluded = []
    )
    {
    }
    /**
     * Set the purge mode
     */
    public function set_purge_mode(int $mode): void
    {
        $this->purge_mode = $mode;
        $this->cached_sql_statements = null;
    }
    /**
     * Get the purge mode
     */
    public function get_purge_mode(): int
    {
        return $this->purge_mode;
    }
    public function set_entity_manager(Entity_Manager_Interface $em): void
    {
        $this->em = $em;
        $this->cached_sql_statements = null;
    }
    /**
     * Retrieve the EntityManagerInterface instance this purger instance is using.
     */
    public function get_object_manager(): Entity_Manager_Interface
    {
        return $this->em;
    }
    public function purge(): void
    {
        $connection = $this->em->get_connection();
        array_map([$connection, 'executeStatement'], $this->get_purge_statements());
    }
    /** @return list<string> */
    private function get_purge_statements(): array
    {
        if ($this->cached_sql_statements !== null) {
            return $this->cached_sql_statements;
        }
        $connection = $this->em->get_connection();
        $classes = [];
        foreach ($this->em->get_metadata_factory()->get_all_metadata() as $metadata) {
            if ($metadata->is_mapped_superclass) {
                continue;
            }
            if (isset($metadata->is_embedded_class) && $metadata->is_embedded_class) {
                continue;
            }
            $classes[] = $metadata;
        }
        $commit_order = $this->get_commit_order($this->em, $classes);
        // Get platform parameters
        $platform = $connection->get_database_platform();
        // Drop association tables first
        $ordered_tables = $this->get_association_tables($commit_order, $platform);
        // Drop tables in reverse commit order
        for ($i = count($commit_order) - 1; $i >= 0; --$i) {
            $class = $commit_order[$i];
            if (isset($class->is_embedded_class) && $class->is_embedded_class) {
                continue;
            }
            if ($class->is_mapped_superclass) {
                continue;
            }
            if ($class->is_inheritance_type_single_table() && $class->name !== $class->root_entity_name) {
                continue;
            }
            $ordered_tables[] = $this->get_table_name($class, $platform);
        }
        $connection_configuration = $connection->get_configuration();
        $schema_assets_filter = $connection_configuration->get_schema_assets_filter() ?? static fn(): bool => true;
        $this->cached_sql_statements = [];
        foreach ($ordered_tables as $tbl) {
            // If the table is excluded, skip it as well
            if (in_array($tbl, $this->excluded)) {
                continue;
            }
            // Support schema asset filters as presented in
            if (!$schema_assets_filter($tbl)) {
                continue;
            }
            if ($this->purge_mode === self::PURGE_MODE_DELETE) {
                $this->cached_sql_statements[] = $this->get_delete_from_table_sql($tbl, $platform);
            } else {
                $this->cached_sql_statements[] = $platform->get_truncate_table_sql($tbl, true);
            }
        }
        return $this->cached_sql_statements;
    }
    /**
     * @param ClassMetadata[] $classes
     *
     * @return ClassMetadata[]
     */
    private function get_commit_order(Entity_Manager_Interface $em, array $classes): array
    {
        $sorter = new Topological_Sorter();
        foreach ($classes as $class) {
            if (!$sorter->has_node($class->name)) {
                $sorter->add_node($class->name, $class);
            }
            // $class before its parents
            foreach ($class->parent_classes as $parent_class) {
                $parent_class = $em->get_class_metadata($parent_class);
                $parent_class_name = $parent_class->get_name();
                if (!$sorter->has_node($parent_class_name)) {
                    $sorter->add_node($parent_class_name, $parent_class);
                }
                $sorter->add_dependency($class->name, $parent_class_name);
            }
            foreach ($class->association_mappings as $assoc) {
                if (!$assoc['isOwningSide']) {
                    continue;
                }
                $target_class = $em->get_class_metadata($assoc['targetEntity']);
                $target_class_name = $target_class->get_name();
                if (!$sorter->has_node($target_class_name)) {
                    $sorter->add_node($target_class_name, $target_class);
                }
                // add dependency ($targetClass before $class)
                $sorter->add_dependency($target_class_name, $class->name);
                // parents of $targetClass before $class, too
                foreach ($target_class->parent_classes as $parent_class) {
                    $parent_class = $em->get_class_metadata($parent_class);
                    $parent_class_name = $parent_class->get_name();
                    if (!$sorter->has_node($parent_class_name)) {
                        $sorter->add_node($parent_class_name, $parent_class);
                    }
                    $sorter->add_dependency($parent_class_name, $class->name);
                }
            }
        }
        return array_reverse($sorter->sort());
    }
    /**
     * @param ClassMetadata[] $classes
     *
     * @return string[]
     */
    private function get_association_tables(array $classes, Abstract_Platform $platform): array
    {
        $association_tables = [];
        foreach ($classes as $class) {
            foreach ($class->association_mappings as $assoc) {
                if (!$assoc['isOwningSide']) {
                    continue;
                }
                if ($assoc['type'] !== Class_Metadata::MANY_TO_MANY) {
                    continue;
                }
                $association_tables[] = $this->get_join_table_name($assoc, $class, $platform);
            }
        }
        return $association_tables;
    }
    private function get_table_name(Class_Metadata $class, Abstract_Platform $platform): string
    {
        return $this->em->get_configuration()->get_quote_strategy()->get_table_name($class, $platform);
    }
    /** @param ManyToManyOwningSideMapping|mixed[] $assoc */
    private function get_join_table_name($assoc, Class_Metadata $class, Abstract_Platform $platform): string
    {
        return $this->em->get_configuration()->get_quote_strategy()->get_join_table_name($assoc, $class, $platform);
    }
    private function get_delete_from_table_sql(string $table_name, Abstract_Platform $platform): string
    {
        $table_identifier = new Identifier($table_name);
        if (class_exists(Abstract_Named_Object::class)) {
            $identifier = $table_identifier->get_object_name()->to_sql($platform);
        } else {
            $identifier = $table_identifier->get_quoted_name($platform);
        }
        return 'DELETE FROM ' . $identifier;
    }
}