<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Sorter;

use Doctrine\Common\Data_Fixtures\Exception\Circular_Reference_Exception;
use Doctrine\ORM\Mapping\Class_Metadata;
use RuntimeException;
use function sprintf;
/**
 * TopologicalSorter is an ordering algorithm for directed graphs (DG) and/or
 * directed acyclic graphs (DAG) by using a depth-first searching (DFS) to
 * traverse the graph built in memory.
 * This algorithm have a linear running time based on nodes (V) and dependency
 * between the nodes (E), resulting in a computational complexity of O(V + E).
 *
 * @internal this class is to be used only by data-fixtures internals: do not
 *           rely on it in your own libraries/applications.
 */
class Topological_Sorter
{
    /**
     * Matrix of nodes (aka. vertex).
     * Keys are provided hashes and values are the node definition objects.
     *
     * @var Vertex[]
     */
    private array $node_list = [];
    /**
     * Volatile variable holding calculated nodes during sorting process.
     *
     * @var ClassMetadata[]
     */
    private array $sorted_node_list = [];
    public function __construct(
        /**
         * Allow or not cyclic dependencies
         */
        private readonly bool $allow_cyclic_dependencies = true
    )
    {
    }
    /**
     * Adds a new node (vertex) to the graph, assigning its hash and value.
     */
    public function add_node(string $hash, Class_Metadata $node): void
    {
        $this->node_list[$hash] = new Vertex($node);
    }
    /**
     * Checks the existence of a node in the graph.
     */
    public function has_node(string $hash): bool
    {
        return isset($this->node_list[$hash]);
    }
    /**
     * Adds a new dependency (edge) to the graph using their hashes.
     */
    public function add_dependency(string $from_hash, string $to_hash): void
    {
        $definition = $this->node_list[$from_hash];
        $definition->dependency_list[] = $to_hash;
    }
    /**
     * Return a valid order list of all current nodes.
     * The desired topological sorting is the postorder of these searches.
     *
     * Note: Highly performance-sensitive method.
     *
     * @return ClassMetadata[]
     *
     * @throws RuntimeException
     * @throws CircularReferenceException
     */
    public function sort(): array
    {
        foreach ($this->node_list as $definition) {
            if ($definition->state !== Vertex::NOT_VISITED) {
                continue;
            }
            $this->visit($definition);
        }
        $sorted_list = $this->sorted_node_list;
        $this->node_list = [];
        $this->sorted_node_list = [];
        return $sorted_list;
    }
    /**
     * Visit a given node definition for reordering.
     *
     * Note: Highly performance-sensitive method.
     *
     * @throws RuntimeException
     * @throws CircularReferenceException
     */
    private function visit(Vertex $definition): void
    {
        $definition->state = Vertex::IN_PROGRESS;
        foreach ($definition->dependency_list as $dependency) {
            if (!isset($this->node_list[$dependency])) {
                throw new RuntimeException(sprintf('Fixture "%s" has a dependency of fixture "%s", but it not listed to be loaded.', $definition->value::class, $dependency));
            }
            $child_definition = $this->node_list[$dependency];
            // allow self referencing classes
            if ($definition === $child_definition) {
                continue;
            }
            switch ($child_definition->state) {
                case Vertex::VISITED:
                    break;
                case Vertex::IN_PROGRESS:
                    if (!$this->allow_cyclic_dependencies) {
                        throw new Circular_Reference_Exception(sprintf(<<<'EXCEPTION'
                        Graph contains cyclic dependency between the classes "%s" and
                         "%s". An example of this problem would be the following:
                        Class C has class B as its dependency. Then, class B has class A has its dependency.
                        Finally, class A has class C as its dependency.
                        EXCEPTION, $definition->value->get_name(), $child_definition->value->get_name()));
                    }
                    break;
                case Vertex::NOT_VISITED:
                    $this->visit($child_definition);
            }
        }
        $definition->state = Vertex::VISITED;
        $this->sorted_node_list[] = $definition->value;
    }
}