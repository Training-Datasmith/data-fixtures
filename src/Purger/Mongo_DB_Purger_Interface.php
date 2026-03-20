<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Purger;

use Doctrine\ODM\Mongo_Db\Document_Manager;
interface Mongo_Db_Purger_Interface extends Purger_Interface
{
    /**
     * Set the DocumentManager instance this purger instance should use.
     */
    public function set_document_manager(Document_Manager $dm): void;
}