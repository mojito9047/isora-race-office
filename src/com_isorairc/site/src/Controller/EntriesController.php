<?php

namespace Isora\Component\Isorairc\Site\Controller;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Isora\Component\Isorairc\Administrator\Helper\FleetsheetHelper;
use Joomla\CMS\MVC\Controller\BaseController;

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
                $value = (string) $entry->$key;
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
     * The race asked for, after checking the link is genuine and the user is race management.
     */
    private function race(): object
    {
        $this->checkToken('get');

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_isorairc')) {
            throw new \RuntimeException('You are not allowed to download entries.', 403);
        }

        $races  = EntriesHelper::races();
        $raceId = $this->input->getInt('race');

        if (!isset($races[$raceId])) {
            throw new \RuntimeException('Race not found.', 404);
        }

        return $races[$raceId];
    }
}
