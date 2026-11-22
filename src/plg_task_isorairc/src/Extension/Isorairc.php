<?php

namespace Isora\Plugin\Task\Isorairc\Extension;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Scheduled task: import the rating feeds and take due race snapshots.
 */
final class Isorairc extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    protected const TASKS_MAP = [
        'isorairc.import' => [
            'langConstPrefix' => 'PLG_TASK_ISORAIRC_IMPORT',
            'method'          => 'importRatings',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    private function importRatings(ExecuteTaskEvent $event): int
    {
        if (!class_exists(RatingsHelper::class)) {
            $this->logTask('The ISORA Race Office component is not installed or is disabled.', 'error');

            return Status::KNOCKOUT;
        }

        try {
            $result = RatingsHelper::run();
        } catch (\Throwable $e) {
            $this->logTask('Ratings import failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        $this->logTask(RatingsHelper::summary($result), $result['errors'] ? 'error' : 'info');

        // A feed that failed is reported as a failed run so it shows up in the task list.
        return $result['errors'] ? Status::KNOCKOUT : Status::OK;
    }
}
