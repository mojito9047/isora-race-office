<?php

namespace Isora\Component\Isorairc\Site\Controller;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Isora\Component\Isorairc\Administrator\Helper\FleetsheetHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class EntriesController extends BaseController
{
    /**
     * Race management only: download one race's entries, with contact details, as CSV.
     */
    public function export()
    {
        $race    = $this->race();
        $raceId  = (int) $race->id;
        $races   = [$raceId => $race];
        $columns = [
            'boat'        => 'Boat',
            'sail'        => 'Sail No',
            'model'       => 'Type',
            'class'       => 'Class',
            'club'        => 'Club',
            'rating_text' => 'Rating',
            'rating_note' => 'Rating note',
            'declared'    => 'Declared rating',
            'issue_date'       => 'IRC issue date',
            'crew'             => 'IRC crew',
            'headsails'        => 'IRC headsails',
            'flying_headsails' => 'IRC flying headsails',
            'spinnakers'       => 'IRC spinnakers',
            'sss_base'         => 'IRC SSS base value',
            'certificate'      => 'IRC certificate',
            'contact'     => 'Person in charge',
            'phone'       => 'Mobile',
            'email'       => 'E-mail',
            'category'    => 'Entry type',
            'tracker'     => 'Own tracker serial',
            'submitted'   => 'Form sent (UTC)',
        ];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="entries-race-' . (int) $raceId . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, array_values($columns), ',', '"', '\\');

        foreach (EntriesHelper::entries($races[$raceId]) as $entry) {
            $row = [];

            foreach (array_keys($columns) as $key) {
                $value = $key === 'certificate' ? ucfirst((string) $entry->$key) : (string) $entry->$key;
                // Stop spreadsheet programs treating typed-in text as a formula.
                $row[] = preg_match('/^[=+\-@]/', $value) && !is_numeric($value) ? "'" . $value : $value;
            }

            fputcsv($out, $row, ',', '"', '\\');
        }

        fclose($out);
        $this->app->close();
    }

    /**
     * Race management only: download the YB Tracking fleet information sheet
     * for one race, filled in from the entries and the ratings.
     */
    public function fleetsheet()
    {
        $race = $this->race();
        $xlsx = FleetsheetHelper::build(EntriesHelper::entries($race));
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $race->name), '_') ?: 'race-' . (int) $race->id;
        $name = substr((string) $race->race_date, 0, 4) . '_ISORA_' . $name . '_Fleetsheet.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . \strlen($xlsx));

        echo $xlsx;
        $this->app->close();
    }

    /**
     * Race management only: put a boat on its secondary IRC certificate for one
     * race, or back on its primary one. Allowed at any time, also after the
     * rating deadline.
     */
    public function certificate()
    {
        $race        = $this->race('post');
        $boat        = trim($this->input->post->getString('boat', ''));
        $sail        = trim($this->input->post->getString('sail', ''));
        $certificate = $this->input->post->getCmd('certificate', '');
        $key         = EntriesHelper::boatKey($boat, $sail);
        $found       = null;

        foreach (EntriesHelper::entries($race) as $entry) {
            if (EntriesHelper::boatKey($entry->boat, $entry->sail) === $key) {
                $found = $entry;
            }
        }

        if (!$found || !\in_array($certificate, ['primary', 'secondary'], true)) {
            throw new \RuntimeException('Boat not found among the entries for this race.', 404);
        }

        $back = Route::_('index.php?option=com_isorairc&view=entries&race=' . (int) $race->id, false);

        if ($certificate === 'secondary' && !$found->has_secondary) {
            $this->setRedirect($back, $found->boat . ' has no secondary certificate on the IRC list.', 'warning');

            return;
        }

        EntriesHelper::setCertificate((int) $race->id, $found->boat, $found->sail, $certificate, (string) $this->app->getIdentity()->name);
        $this->setRedirect($back, $found->boat . ' is now on its ' . $certificate . ' certificate for ' . $race->name . '.');
    }

    /**
     * The race asked for, after checking the request is genuine and the user is race management.
     */
    private function race(string $method = 'get'): object
    {
        $this->checkToken($method);

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_isorairc')) {
            throw new \RuntimeException('You are not allowed to do that.', 403);
        }

        $races  = EntriesHelper::races();
        $raceId = $this->input->getInt('race');

        if (!isset($races[$raceId])) {
            throw new \RuntimeException('Race not found.', 404);
        }

        return $races[$raceId];
    }
}
