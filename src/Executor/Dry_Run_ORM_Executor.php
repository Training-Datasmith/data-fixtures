<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Executor;

/**
 * This executor allows to execute (and indirectly, print) SQL statements without
 * actually committing them to the database
 */
final class Dry_Run_Orm_Executor extends Abstract_Executor
{
    use Orm_Executor_Common;
    /** @inheritDoc */
    public function execute(array $fixtures, bool $append = false): void
    {
        $executor = $this;
        $this->em->begin_transaction();
        try {
            if ($append === false) {
                $executor->purge();
            }
            foreach ($fixtures as $fixture) {
                $executor->load($this->em, $fixture);
            }
            $this->em->flush();
        } finally {
            $this->em->roll_back();
            $this->em->close();
        }
    }
}