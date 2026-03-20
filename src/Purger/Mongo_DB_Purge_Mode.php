<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Purger;

enum Mongo_Db_Purge_Mode : string
{
    /**
     * Purge the collections using deleteMany(). Don't create them.
     */
    case Delete = 'delete';
    /**
     * Drop the collections when purging, then recreate them.
     */
    case Drop = 'drop';
}