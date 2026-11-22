<?php

namespace Isora\Component\Isorairc\Site\Model;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class EntriesModel extends BaseDatabaseModel
{
    /**
     * Races linked to the entry form, in date order.
     *
     * @return object[] keyed by race id
     */
    public function getRaces(): array
    {
        return EntriesHelper::races();
    }

    /**
     * The race to show when none is asked for: the next one to be sailed,
     * or the last one once the season is over.
     */
    public function defaultRaceId(array $races): int
    {
        $today = gmdate('Y-m-d');
        $last  = 0;

        foreach ($races as $race) {
            if ($race->race_date >= $today) {
                return (int) $race->id;
            }

            $last = (int) $race->id;
        }

        return $last;
    }

    /**
     * @return object[]
     */
    public function getEntries(object $race): array
    {
        return EntriesHelper::entries($race);
    }
}
