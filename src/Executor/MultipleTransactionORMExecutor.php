<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Executor;

use Doctrine\ORM\Entity_Manager_Interface;
final class Multiple_Transaction_Orm_Executor extends Abstract_Executor
{
    use Orm_Executor_Common;
    /** @inheritDoc */
    public function execute(array $fixtures, bool $append = false): void
    {
        $executor = $this;
        if ($append === false) {
            $this->em->wrap_in_transaction(static fn() => $executor->purge());
        }
        foreach ($fixtures as $fixture) {
            $this->em->wrap_in_transaction(static fn(Entity_Manager_Interface $em) => $executor->load($em, $fixture));
        }
    }
}