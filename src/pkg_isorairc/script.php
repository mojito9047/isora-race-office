<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class Pkg_IsoraircInstallerScript
{
    /**
     * Enable the scheduled task plugin on first install (plugins install disabled).
     */
    public function postflight($type, $parent)
    {
        if ($type !== 'install') {
            return true;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('isorairc'));
        $db->setQuery($query)->execute();

        return true;
    }
}
