<?php

namespace Isora\Component\Isorairc\Site\View\Ratings;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Isora\Component\Isorairc\Site\Model\RatingsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $term = '';

    public $raceId = 0;

    public $races = [];

    /** Enabled rating systems, keyed by scheme. */
    public $systems = [];

    /** Search results keyed by scheme. */
    public $results = [];

    /** Last import time (UTC) keyed by scheme. */
    public $lastImports = [];

    public $limit = RatingsModel::LIMIT;

    public function display($tpl = null)
    {
        $input = Factory::getApplication()->getInput();
        $model = $this->getModel();

        $this->term        = trim($input->getString('q', ''));
        $this->systems     = RatingsHelper::systems();
        $this->races       = $model->getRaces();
        $this->raceId      = $input->getInt('race', 0);
        $this->lastImports = $model->getLastImports();

        if (!isset($this->races[$this->raceId])) {
            $this->raceId = 0;
        }

        foreach ($this->systems as $scheme => $system) {
            // A race only has results for the systems that were frozen for it.
            if ($this->raceId && !\in_array($scheme, $this->races[$this->raceId]->schemes, true)) {
                $this->results[$scheme] = null;

                continue;
            }

            $this->results[$scheme] = $model->search($scheme, $this->term, $this->raceId);
        }

        parent::display($tpl);
    }
}
