<?php

namespace Isora\Component\Isorairc\Administrator\View\Races;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public $items = [];

    public $pagination;

    /** Enabled rating systems, keyed by scheme. */
    public $systems = [];

    /** Last import per scheme (object or null). */
    public $lastImports = [];

    /** Snapshot status as [race id][scheme] => row. */
    public $snapshots = [];

    public function display($tpl = null)
    {
        $model            = $this->getModel();
        $this->items      = $model->getItems() ?: [];
        $this->pagination = $model->getPagination();
        $this->systems    = RatingsHelper::systems();

        foreach ($this->systems as $scheme => $system) {
            $this->lastImports[$scheme] = RatingsHelper::lastImport($scheme);
        }

        $this->snapshots = RatingsHelper::snapshotStatus(array_column($this->items, 'id'));

        ToolbarHelper::title('ISORA Race Office', 'list');
        ToolbarHelper::addNew('race.add');
        ToolbarHelper::editList('race.edit');
        ToolbarHelper::custom('races.import', 'download', '', 'Import ratings now', false);
        ToolbarHelper::custom('races.rebuild', 'refresh', '', 'Rebuild snapshot', true);
        ToolbarHelper::deleteList('Delete the selected races and their rating snapshots?', 'races.delete');
        ToolbarHelper::preferences('com_isorairc');

        parent::display($tpl);
    }
}
