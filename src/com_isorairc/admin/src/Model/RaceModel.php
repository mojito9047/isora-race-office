<?php

namespace Isora\Component\Isorairc\Administrator\Model;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;

class RaceModel extends AdminModel
{
    protected $text_prefix = 'COM_ISORAIRC';

    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_isorairc.race', 'race', ['control' => 'jform', 'load_data' => $loadData]);

        if (!$form) {
            return false;
        }

        // New races start with the default number of days from the component options.
        $form->setFieldAttribute(
            'deadline_days',
            'default',
            (string) (int) ComponentHelper::getParams('com_isorairc')->get('deadline_days', 7)
        );

        // Offer the yes/no questions of the entry form chosen in the options.
        $field = $form->getFieldXml('entry_field');

        if ($field) {
            $current = (string) $form->getValue('entry_field');
            $names   = EntriesHelper::raceFields();

            if ($current !== '' && !isset($names[$current])) {
                $names[$current] = $current;
            }

            foreach ($names as $name => $caption) {
                $option = $field->addChild('option', htmlspecialchars($caption . ' (' . $name . ')', ENT_QUOTES | ENT_XML1));
                $option->addAttribute('value', $name);
            }
        }

        return $form;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_isorairc.edit.race.data', []);

        return $data ?: $this->getItem();
    }

    /**
     * Deleting a race also deletes its snapshots and its boats' certificate choices.
     */
    public function delete(&$pks)
    {
        $ids = array_filter(array_map('intval', (array) $pks));

        if (!parent::delete($pks)) {
            return false;
        }

        RatingsHelper::deleteSnapshots($ids);
        EntriesHelper::deleteCertificateChoices($ids);

        return true;
    }
}
