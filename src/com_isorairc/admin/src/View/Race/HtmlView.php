<?php

namespace Isora\Component\Isorairc\Administrator\View\Race;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public $form;

    public $item;

    public function display($tpl = null)
    {
        $model      = $this->getModel();
        $this->form = $model->getForm();
        $this->item = $model->getItem();

        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        ToolbarHelper::title(empty($this->item->id) ? 'New race' : 'Edit race', 'pencil-alt');
        ToolbarHelper::apply('race.apply');
        ToolbarHelper::save('race.save');
        ToolbarHelper::cancel('race.cancel', empty($this->item->id) ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
