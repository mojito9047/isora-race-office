<?php

namespace IsoraTests\Cases;

use IsoraTests\Browser;
use IsoraTests\Env;
use IsoraTests\SiteTest;

/**
 * The daily import as a Joomla scheduled task, run the way a server's cron job runs it.
 */
class ScheduledTaskTest extends SiteTest
{
    /** Add the import to Joomla's scheduled tasks, due now. */
    private function addTask(): int
    {
        $task = (object) [
            'title'           => 'Test: import boat ratings',
            'type'            => 'isorairc.import',
            'execution_rules' => '{"rule-type":"interval-days","interval-days":"1","exec-day":"1","exec-time":"03:00"}',
            'cron_rules'      => '{"type":"interval","exp":"P1D"}',
            'state'           => 1,
            'next_execution'  => Env::utc('-1 hour'),
            'params'          => '{"individual_log":false,"log_file":"","notifications":{"success_mail":"0","failure_mail":"0","fatal_failure_mail":"0","orphan_mail":"0"}}',
            'created'         => Env::utc('now'),
        ];
        Env::db()->insertObject('#__scheduler_tasks', $task, 'id');

        return (int) $task->id;
    }

    /** Run one task through Joomla's command-line tool. */
    private function runTask(int $id): string
    {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(JPATH_ROOT . '/cli/joomla.php') . ' scheduler:run --id=' . $id . ' --no-interaction 2>&1', $output);

        return implode("\n", $output);
    }

    private function task(int $id): object
    {
        return Env::rows('#__scheduler_tasks', 'id = ' . $id)[0];
    }

    public function testTaskTypeIsOfferedWithItsNewName(): void
    {
        $admin = new Browser();
        $admin->loginAdmin(Env::$adminUser, Env::$adminPass);
        $page = $admin->get('/administrator/index.php?option=com_scheduler&view=select&layout=default');

        $this->assertNoPhpErrors($page);
        $this->assertContains('ISORA Race Office: import boat ratings', $page->body);
        $this->assertNotContains('PLG_TASK_ISORAIRC', $page->body, 'No untranslated labels');
    }

    public function testRunningTheTaskImportsBothLists(): void
    {
        $id     = $this->addTask();
        $output = $this->runTask($id);
        $task   = $this->task($id);

        $this->assertCount(230, Env::rows('#__isorairc_ratings'), 'Ratings imported. Output: ' . $output);
        $this->assertSame(0, (int) $task->last_exit_code, 'Task result');
        $this->assertSame(1, (int) $task->times_executed);
        $this->assertSame(0, (int) $task->times_failed);
        $this->assertTrue($task->next_execution > Env::utc('now'), 'Scheduled again for later');
    }

    public function testRunningTheTaskFreezesRacesThatAreDue(): void
    {
        $race = Env::addRace('Due race', Env::localDate(Env::utc('-1 day')), Env::utc('-8 days'));
        $this->runTask($this->addTask());

        $this->assertCount(2, Env::rows('#__isorairc_race_snapshots', 'race_id = ' . $race, 'scheme'));
    }

    public function testFailedListShowsAsAFailedRunAndKeepsTheOldRatings(): void
    {
        $id = $this->addTask();
        $this->runTask($id);
        Env::feed('irc', '', 500);
        $db = Env::db();
        $db->setQuery('UPDATE ' . $db->quoteName('#__scheduler_tasks') . ' SET ' . $db->quoteName('next_execution') . ' = ' . $db->quote(Env::utc('-1 hour')) . ' WHERE id = ' . $id)->execute();

        $this->runTask($id);
        $task = $this->task($id);

        $this->assertTrue((int) $task->last_exit_code !== 0, 'The run is recorded as failed');
        $this->assertSame(1, (int) $task->times_failed);
        $this->assertCount(120, Env::rows('#__isorairc_ratings', "scheme = 'irc'"), 'Earlier IRC ratings are kept');
    }
}
