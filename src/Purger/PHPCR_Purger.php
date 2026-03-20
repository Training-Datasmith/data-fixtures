<?php

declare (strict_types=1);
namespace Doctrine\Common\Data_Fixtures\Purger;

use Doctrine\ODM\PHPCR\Document_Manager_Interface;
use PHPCR\Util\Node_Helper;
/**
 * Class responsible for purging databases of data before reloading data fixtures.
 */
final class Phpcr_Purger implements Phpcr_Purger_Interface
{
    public function __construct(private Document_Manager_Interface|null $dm = null)
    {
    }
    public function set_document_manager(Document_Manager_Interface $dm): void
    {
        $this->dm = $dm;
    }
    public function get_object_manager(): Document_Manager_Interface|null
    {
        return $this->dm;
    }
    public function purge(): void
    {
        $session = $this->dm->get_phpcr_session();
        Node_Helper::purge_workspace($session);
        $session->save();
    }
}