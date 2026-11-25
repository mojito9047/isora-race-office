<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class Pkg_IsoraircInstallerScript
{
    /** Version that was installed before this update, or '' on a first install. */
    private string $before = '';

    public function preflight($type, $parent)
    {
        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_isorairc'));
            $cache = json_decode((string) $db->setQuery($query)->loadResult(), true);

            $this->before = \is_array($cache) ? (string) ($cache['version'] ?? '') : '';
        } catch (\Throwable $e) {
            $this->before = '';
        }

        return true;
    }

    /**
     * Enable the scheduled task plugin on first install (plugins install disabled).
     * Coming from a version before 1.9.0, link the existing races to the entry form in the options.
     */
    public function postflight($type, $parent)
    {
        if ($type !== 'install') {
            if ($this->before !== '' && version_compare($this->before, '1.9.0', '<')) {
                $this->pinRaces();
            }

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

    /**
     * Until 1.9.0 every race read the one form in the options. Store that form
     * with each race that is on it, so that choosing next season's form later
     * does not change these races. (Plain SQL: the component's classes may not be loadable here.)
     */
    private function pinRaces(): void
    {
        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_isorairc'));
            $params = json_decode((string) $db->setQuery($query)->loadResult(), true);
            $formId = \is_array($params) ? (int) ($params['entry_form_id'] ?? 0) : 0;

            if ($formId <= 0) {
                return;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__isorairc_races'))
                ->set($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('form_id') . ' IS NULL')
                ->where($db->quoteName('entry_field') . ' IS NOT NULL')
                ->where($db->quoteName('entry_field') . " <> ''");
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Races without a stored form go on reading the form in the options.
        }
    }
}
