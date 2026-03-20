<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Executor;

use Doctrine\Common\Data_Fixtures\Purger\Phpcr_Purger_Interface;
use Doctrine\ODM\PHPCR\Document_Manager_Interface;
use function method_exists;
/**
 * Class responsible for executing data fixtures.
 */
final class Phpcr_Executor extends Abstract_Executor
{
    /**
     * @param DocumentManagerInterface  $dm     manager instance used for persisting the fixtures
     * @param PHPCRPurgerInterface|null $purger to remove the current data if append is false
     */
    public function __construct(private readonly Document_Manager_Interface $dm, Phpcr_Purger_Interface|null $purger = null)
    {
        parent::__construct($dm);
        if ($purger === null) {
            return;
        }
        $purger->set_document_manager($dm);
        $this->set_purger($purger);
    }
    public function get_object_manager(): Document_Manager_Interface
    {
        return $this->dm;
    }
    /** @inheritDoc */
    public function execute(array $fixtures, bool $append = false): void
    {
        $that = $this;
        $function = static function (\Doctrine\Persistence\Object_Manager $dm) use ($append, $that, $fixtures): void {
            if ($append === false) {
                $that->purge();
            }
            foreach ($fixtures as $fixture) {
                $that->load($dm, $fixture);
            }
        };
        if (method_exists($this->dm, 'transactional')) {
            $this->dm->transactional($function);
        } else {
            $function($this->dm);
        }
    }
}