<?php

namespace Isora\Component\Isorairc\Site\View\Entries;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    /** Races linked to the entry form, keyed by id. */
    public $races = [];

    /** The race being shown, or null. */
    public $race = null;

    public $entries = [];

    /** Number of entries per class. */
    public $classCounts = [];

    /** Enabled rating systems, keyed by scheme. */
    public $systems = [];

    /** 'snapshot' or 'current' per scheme for the race being shown. */
    public $sources = [];

    /** True when the entry form has been chosen in the component options. */
    public $configured = false;

    /** True for race management: adds contact details and the CSV download. */
    public $canManage = false;

    public function display($tpl = null)
    {
        $app   = Factory::getApplication();
        $model = $this->getModel();

        $this->configured = EntriesHelper::formId() > 0;
        $this->canManage  = (bool) $app->getIdentity()->authorise('core.manage', 'com_isorairc');
        $this->systems    = RatingsHelper::systems();
        $this->races      = $model->getRaces();

        $raceId = $app->getInput()->getInt('race', 0);

        if (!isset($this->races[$raceId])) {
            $raceId = $model->defaultRaceId($this->races);
        }

        if ($raceId) {
            $this->race    = $this->races[$raceId];
            $this->entries = $model->getEntries($this->race);
            $this->sources = EntriesHelper::ratingSources($this->race);

            foreach ($this->entries as $entry) {
                $class                     = $entry->class !== '' ? $entry->class : 'No class given';
                $this->classCounts[$class] = ($this->classCounts[$class] ?? 0) + 1;
            }
        }

        parent::display($tpl);
    }
}
