<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Executor;

use Doctrine\ORM\Entity_Manager_Interface;
/**
 * Class responsible for executing data fixtures.
 */
final class Orm_Executor extends Abstract_Executor
{
    use Orm_Executor_Common;
    /** @inheritDoc */
    public function execute(array $fixtures, bool $append = false): void
    {
        $executor = $this;
        $this->em->wrap_in_transaction(static function (Entity_Manager_Interface $em) use ($executor, $fixtures, $append): void {
            if ($append === false) {
                $executor->purge();
            }
            foreach ($fixtures as $fixture) {
                $executor->load($em, $fixture);
            }
        });
    }
}