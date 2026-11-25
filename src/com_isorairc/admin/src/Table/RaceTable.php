<?php

namespace Isora\Component\Isorairc\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class RaceTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__isorairc_races', 'id', $db);
    }

    /**
     * The rating deadline is always derived: race date minus "deadline_days",
     * at the time of day set in the component options (site time zone), stored as UTC.
     */
    public function store($updateNulls = false)
    {
        $time = (string) ComponentHelper::getParams('com_isorairc')->get('deadline_time', '23:59');

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '23:59';
        }

        $this->deadline_days = max(0, (int) $this->deadline_days);

        $timezone = new \DateTimeZone(Factory::getApplication()->get('offset', 'UTC'));
        $deadline = new \DateTime(substr((string) $this->race_date, 0, 10) . ' ' . $time . ':00', $timezone);
        $deadline->modify('-' . $this->deadline_days . ' days');
        $deadline->setTimezone(new \DateTimeZone('UTC'));

        $this->rating_deadline = $deadline->format('Y-m-d H:i:s');

        // A race on an entry form remembers which form: the one given, else the one in the options now.
        // That way choosing next season's form in the options leaves this race's entries alone.
        $formId = (int) ($this->form_id ?? 0);

        if ($formId <= 0 && trim((string) ($this->entry_field ?? '')) !== '') {
            $formId = (int) ComponentHelper::getParams('com_isorairc')->get('entry_form_id', 0);
        }

        $this->form_id = $formId > 0 ? $formId : null;

        // Nulls are written so that an emptied form ID is really cleared.
        return parent::store(true);
    }
}
