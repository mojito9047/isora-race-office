<?php

namespace Isora\Component\Isorairc\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class RacesModel extends ListModel
{
    protected function getListQuery()
    {
        $db = $this->getDatabase();

        return $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__isorairc_races'))
            ->order($db->quoteName('rating_deadline') . ' DESC');
    }
}
