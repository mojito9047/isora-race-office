<?php

namespace Isora\Component\Isorairc\Administrator\Controller;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;

class RacesController extends AdminController
{
    protected $text_prefix = 'COM_ISORAIRC';

    public function getModel($name = 'Race', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Toolbar button: download the feeds now and take any snapshots that are due.
     */
    public function import()
    {
        $this->checkToken();

        try {
            $result = RatingsHelper::run();
            $this->setMessage(RatingsHelper::summary($result), $result['errors'] ? 'warning' : 'message');
        } catch (\Throwable $e) {
            $this->setMessage('Import failed: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_isorairc&view=races', false));
    }

    /**
     * Toolbar button: rebuild the snapshots of the ticked races from the archive.
     */
    public function rebuild()
    {
        $this->checkToken();

        try {
            $taken = RatingsHelper::rebuild((array) $this->input->get('cid', [], 'array'));
            $this->setMessage($taken . ' race snapshot(s) rebuilt from the archive.');
        } catch (\Throwable $e) {
            $this->setMessage('Rebuild failed: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_isorairc&view=races', false));
    }

    /**
     * Download one race's snapshot for one rating system as CSV.
     */
    public function export()
    {
        $id      = $this->input->getInt('id');
        $scheme  = $this->input->getCmd('scheme', 'irc');
        $systems = RatingsHelper::systems(false);

        if (!isset($systems[$scheme])) {
            throw new \RuntimeException('Unknown rating system.', 404);
        }

        // Export the columns this system's feed provides, under the feed's own headings.
        $columns = $systems[$scheme]['columns'];
        $db      = Factory::getContainer()->get('DatabaseDriver');
        $query   = $db->getQuery(true)
            ->select($db->quoteName(array_keys($columns)))
            ->from($db->quoteName('#__isorairc_snapshot_ratings'))
            ->where($db->quoteName('race_id') . ' = ' . (int) $id)
            ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme))
            ->order($db->quoteName('boat_name') . ' ASC');
        $rows = $db->setQuery($query)->loadAssocList();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $scheme . '-ratings-race-' . (int) $id . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, array_values($columns), ',', '"', '\\');

        foreach ($rows as $row) {
            foreach (['tcc', 'non_spi_tcc'] as $rating) {
                $row[$rating] = RatingsHelper::formatRating($row[$rating], $systems[$scheme]);
            }

            fputcsv($out, array_values($row), ',', '"', '\\');
        }

        fclose($out);
        $this->app->close();
    }
}
