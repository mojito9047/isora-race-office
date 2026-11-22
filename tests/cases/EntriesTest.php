<?php

namespace IsoraTests\Cases;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use IsoraTests\Env;
use IsoraTests\SiteTest;

/**
 * Reading the entry form: which boats are entered for a race.
 */
class EntriesTest extends SiteTest
{
    private int $race;

    protected function setUp(): void
    {
        parent::setUp();
        Env::standardForm();
        $this->race = Env::addRace('Race 1', '2027-05-01', '2027-04-24 22:59:00', 'Race_1');
    }

    /** @return object[] */
    private function entries(?int $race = null): array
    {
        return EntriesHelper::entries(Env::race($race ?? $this->race));
    }

    private function names(?int $race = null): array
    {
        return array_column($this->entries($race), 'boat');
    }

    public function testFieldNamesHaveTheRealFormsNamesAsDefaults(): void
    {
        $fields = EntriesHelper::fields();

        $this->assertSame('Boat_Name', $fields['boat']);
        $this->assertSame('Sail_Number', $fields['sail']);
        $this->assertSame('Race_Entry_Categoy', $fields['category'], 'The real form spells it this way');
        $this->assertSame('Upload_Yacht_Photograph_', $fields['photo']);
        $this->assertCount(12, $fields);
    }

    public function testFieldNamesCanBeChangedInTheOptions(): void
    {
        Env::setParams(['entry_boat_field' => 'Yacht', 'entry_sail_field' => '  ']);

        $this->assertSame('Yacht', EntriesHelper::fields()['boat']);
        $this->assertSame('Sail_Number', EntriesHelper::fields()['sail'], 'A blank setting falls back to the default');
    }

    public function testOnlyRaceQuestionsAreOfferedWhenLinkingARace(): void
    {
        // Version 1.3.1: the list used to show every single-choice question.
        $this->assertSame(
            ['Race_1' => 'Race 1 - Dun Laoghaire to Holyhead', 'Race_2' => 'Race 2 - Holyhead to Dun Laoghaire'],
            EntriesHelper::raceFields()
        );
    }

    public function testRaceQuestionsFollowTheEnteredAnswerSetting(): void
    {
        Env::question('Race_3', 'Race 3', 'radioGroup', "Entering\nNot entering");
        Env::setParams(['entry_yes_value' => 'entering']);

        $this->assertSame(['Race_3' => 'Race 3'], EntriesHelper::raceFields());
    }

    public function testQuestionsOfOtherFormsAreNotOffered(): void
    {
        Env::question('Race_Old', 'Last year', 'radioGroup', "Yes\nNo", 12);

        $this->assertFalse(isset(EntriesHelper::raceFields()['Race_Old']));
    }

    public function testNoFormChosenMeansNoQuestionsAndNoEntries(): void
    {
        Env::submit(Env::boat());
        Env::setParams(['entry_form_id' => 0]);

        $this->assertSame(0, EntriesHelper::formId());
        $this->assertSame([], EntriesHelper::raceFields());
        $this->assertSame([], $this->entries());
    }

    public function testMissingEntryFormExtensionGivesNoQuestionsInsteadOfAnError(): void
    {
        Env::dropFormTables();

        try {
            $this->assertSame([], EntriesHelper::raceFields());
        } finally {
            Env::createFormTables();
        }
    }

    public function testOnlyRacesLinkedToAQuestionAreListedInDateOrder(): void
    {
        Env::addRace('Not linked', '2027-04-01', '2027-03-25 23:59:00');
        Env::addRace('Blank link', '2027-04-02', '2027-03-26 23:59:00', '');
        $early = Env::addRace('Earlier', '2027-04-10', '2027-04-03 22:59:00', 'Race_2');

        $this->assertSame([$early, $this->race], array_map('intval', array_keys(EntriesHelper::races())));
    }

    public function testOnlyBoatsThatAnsweredYesAreEntered(): void
    {
        Env::submit(Env::boat(['Boat_Name' => 'Yes Boat', 'Sail_Number' => '1']));
        Env::submit(Env::boat(['Boat_Name' => 'No Boat', 'Sail_Number' => '2', 'Race_1' => 'No']));
        Env::submit(Env::boat(['Boat_Name' => 'Blank Boat', 'Sail_Number' => '3', 'Race_1' => '']));
        $unanswered = Env::boat(['Boat_Name' => 'Unanswered', 'Sail_Number' => '4']);
        unset($unanswered['Race_1']);
        Env::submit($unanswered);

        $this->assertSame(['Yes Boat'], $this->names());
    }

    public function testAnswerIsMatchedWhateverItsCapitals(): void
    {
        Env::submit(Env::boat(['Race_1' => ' YES ']));

        $this->assertCount(1, $this->entries());
    }

    public function testEachRaceHasItsOwnList(): void
    {
        $second = Env::addRace('Race 2', '2027-05-15', '2027-05-08 22:59:00', 'Race_2');
        Env::submit(Env::boat(['Boat_Name' => 'First Only', 'Sail_Number' => '1']));
        Env::submit(Env::boat(['Boat_Name' => 'Second Only', 'Sail_Number' => '2', 'Race_1' => 'No', 'Race_2' => 'Yes']));
        Env::submit(Env::boat(['Boat_Name' => 'Both', 'Sail_Number' => '3', 'Race_2' => 'Yes']));

        $this->assertSame(['Both', 'First Only'], $this->names());
        $this->assertSame(['Both', 'Second Only'], $this->names($second));
    }

    public function testBoatThatSentTheFormTwiceCountsOnceByItsLatestForm(): void
    {
        Env::submit(Env::boat(['Sailing_Club' => 'Old Club']), '2026-10-01 10:00:00');
        Env::submit(Env::boat(['Sailing_Club' => 'New Club']), '2026-10-05 10:00:00');
        $entries = $this->entries();

        $this->assertCount(1, $entries);
        $this->assertSame('New Club', $entries[0]->club);
        $this->assertSame('2026-10-05 10:00:00', $entries[0]->submitted);
    }

    public function testLaterFormWithdrawsABoat(): void
    {
        Env::submit(Env::boat(), '2026-10-01 10:00:00');
        Env::submit(Env::boat(['Race_1' => 'No']), '2026-10-05 10:00:00');

        $this->assertSame([], $this->names());
    }

    public function testLaterFormAddsABoat(): void
    {
        Env::submit(Env::boat(['Race_1' => 'No']), '2026-10-01 10:00:00');
        Env::submit(Env::boat(), '2026-10-05 10:00:00');

        $this->assertSame(['Test Boat'], $this->names());
    }

    public function testLatestFormIsDecidedByDateNotByTheOrderTheyWereStored(): void
    {
        Env::submit(Env::boat(['Sailing_Club' => 'Sent later']), '2026-10-05 10:00:00');
        Env::submit(Env::boat(['Sailing_Club' => 'Sent earlier']), '2026-10-01 10:00:00');

        $this->assertSame('Sent later', $this->entries()[0]->club);
    }

    public function testSameBoatTypedSlightlyDifferentlyIsStillOneBoat(): void
    {
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']), '2026-10-01 10:00:00');
        Env::submit(Env::boat(['Boat_Name' => 'SEA BREEZE ', 'Sail_Number' => 'irl-1234']), '2026-10-02 10:00:00');

        $this->assertCount(1, $this->entries());
    }

    public function testTwoBoatsWithTheSameNameButDifferentSailNumbersAreBothListed(): void
    {
        Env::submit(Env::boat(['Boat_Name' => 'Jedi', 'Sail_Number' => 'IRL 1']));
        Env::submit(Env::boat(['Boat_Name' => 'Jedi', 'Sail_Number' => 'GBR 2']));

        $this->assertSame(['GBR 2', 'IRL 1'], array_column($this->entries(), 'sail'), 'Sorted by name, then sail number');
    }

    public function testFormsWithNeitherBoatNameNorSailNumberAreIgnored(): void
    {
        Env::submit(Env::boat(['Boat_Name' => '', 'Sail_Number' => '']));
        Env::submit(Env::boat(['Boat_Name' => '', 'Sail_Number' => 'IRL 9']));

        $this->assertSame(['IRL 9'], array_column($this->entries(), 'sail'));
    }

    public function testEntriesOfOtherFormsAreIgnored(): void
    {
        Env::submit(Env::boat(['Boat_Name' => 'Last Year']), '2025-10-01 10:00:00', 12);

        $this->assertSame([], $this->names());
    }

    public function testBoatsAreSortedByNameIgnoringCapitals(): void
    {
        foreach (['zebra', 'Alpha', 'mike'] as $i => $name) {
            Env::submit(Env::boat(['Boat_Name' => $name, 'Sail_Number' => (string) $i]));
        }

        $this->assertSame(['Alpha', 'mike', 'zebra'], $this->names());
    }

    public function testEveryDetailOfTheEntryIsRead(): void
    {
        $id    = Env::submit(Env::boat(['Upload_Yacht_Photograph_' => '/var/www/site/components/com_rsform/uploads/boat.jpg']));
        $entry = $this->entries()[0];

        $this->assertSame($id, $entry->submission_id);
        $this->assertSame('Test Boat', $entry->boat);
        $this->assertSame('IRL 1234', $entry->sail);
        $this->assertSame('J/109', $entry->model);
        $this->assertSame('IRC', $entry->class);
        $this->assertSame('Test Yacht Club', $entry->club);
        $this->assertSame('1.010', $entry->declared);
        $this->assertSame('Pat Skipper', $entry->contact);
        $this->assertSame('pat.skipper@example.invalid', $entry->email);
        $this->assertSame('+353 85 555 0100', $entry->phone);
        $this->assertSame('Season entry (all races)', $entry->category);
        $this->assertSame('Ireland', $entry->country);
        $this->assertSame('/var/www/site/components/com_rsform/uploads/boat.jpg', $entry->photo);
    }

    public function testChangedFieldNamesAreUsed(): void
    {
        Env::setParams(['entry_boat_field' => 'Yacht', 'entry_yes_value' => 'Entering']);
        Env::submit(['Yacht' => 'Renamed Field', 'Sail_Number' => '5', 'Race_1' => 'entering']);

        $this->assertSame(['Renamed Field'], $this->names());
    }

    public function testRaceWithoutALinkedQuestionHasNoEntries(): void
    {
        Env::submit(Env::boat());
        $unlinked = Env::addRace('Unlinked', '2027-06-01', '2027-05-25 22:59:00');

        $this->assertSame([], $this->names($unlinked));
    }

    public function testNamesWithQuotesAndAccentsComeThroughUnchanged(): void
    {
        Env::submit(Env::boat(['Boat_Name' => "L'Étoile \"Bleue\" <3", 'FullName' => "Seán O'Brien"]));
        $entry = $this->entries()[0];

        $this->assertSame("L'Étoile \"Bleue\" <3", $entry->boat);
        $this->assertSame("Seán O'Brien", $entry->contact);
    }
}
