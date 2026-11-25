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
        $this->assertSame('OwnTracker', $fields['owntracker']);
        $this->assertSame('TrackerSerialNumber', $fields['tracker']);
        $this->assertCount(14, $fields);
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

    public function testOwnTrackerQuestionIsNotOfferedAsARace(): void
    {
        // It is answered Yes or No like a race question, but it is not a race.
        Env::question('OwnTracker', 'Do you wish to use your own tracker');
        Env::question('TrackerSerialNumber', 'Your Tracker Serial Number', 'textBox');

        $this->assertSame(['Race_1', 'Race_2'], array_keys(EntriesHelper::raceFields()));
    }

    public function testRenamedOwnTrackerQuestionIsNotOfferedAsARaceEither(): void
    {
        Env::question('Own_YB', 'Bringing your own tracker?');
        Env::setParams(['entry_owntracker_field' => 'Own_YB']);

        $this->assertSame(['Race_1', 'Race_2'], array_keys(EntriesHelper::raceFields()));
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
        $this->assertSame('', $entry->tracker, 'A form sent before the tracker questions were added');
    }

    public function testIrcBoatHasTheDetailsOfItsCertificate(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, null, [
            'issue_date' => '2026-03-15', 'crew' => 9, 'headsails' => 3, 'flying_headsails' => 0, 'spinnakers' => 4, 'sss_base' => 32,
        ]);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));
        $entry = $this->entries()[0];

        $this->assertSame(
            ['2026-03-15', '9', '3', '0', '4', '32'],
            [$entry->issue_date, $entry->crew, $entry->headsails, $entry->flying_headsails, $entry->spinnakers, $entry->sss_base]
        );
        $this->assertTrue(EntriesHelper::hasIrcDetails($entry));
        $this->assertSame(['issue_date', 'crew', 'headsails', 'flying_headsails', 'spinnakers', 'sss_base'], array_keys(EntriesHelper::IRC_DETAILS));
    }

    public function testCertificateDetailsAreEmptyForUnlistedAndYtcBoats(): void
    {
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845, null, ['issue_date' => '2026-04-01']);
        Env::addRating('irc', 'Bare Row', 'IRL2', 1.000);
        Env::submit(Env::boat(['Boat_Name' => 'Slow Coach', 'Sail_Number' => 'GBR 55', 'Class_Racing' => 'YTC']));
        Env::submit(Env::boat(['Boat_Name' => 'Unlisted', 'Sail_Number' => 'IRL 777']));
        Env::submit(Env::boat(['Boat_Name' => 'Bare Row', 'Sail_Number' => 'IRL 2']));
        $entries = $this->entries();

        $this->assertCount(3, $entries);

        foreach ($entries as $entry) {
            $this->assertSame(
                ['', '', '', '', '', ''],
                [$entry->issue_date, $entry->crew, $entry->headsails, $entry->flying_headsails, $entry->spinnakers, $entry->sss_base],
                $entry->boat
            );
            $this->assertFalse(EntriesHelper::hasIrcDetails($entry), $entry->boat);
        }
    }

    public function testCertificateDetailsComeFromTheFrozenListOnceTheRaceIsFrozen(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.050, null, ['issue_date' => '2027-04-30', 'crew' => 10, 'spinnakers' => 5]);
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, null, ['issue_date' => '2026-03-15', 'crew' => 9, 'spinnakers' => 4], $this->race);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));

        $now = $this->entries()[0];
        $this->assertSame(['2027-04-30', '10', '5'], [$now->issue_date, $now->crew, $now->spinnakers]);

        Env::freeze($this->race, 'irc');
        $frozen = $this->entries()[0];
        $this->assertSame(['2026-03-15', '9', '4'], [$frozen->issue_date, $frozen->crew, $frozen->spinnakers]);
    }

    // ------------------------------------------------------------ primary and secondary certificates

    /** Mojito with a primary and a secondary certificate, as the IRC list writes them, entered for the race. */
    private function boatWithTwoCertificates(int $raceId = 0): void
    {
        Env::addRating('irc', 'MOJITO', 'GBR4822R', 1.084, 1.060, ['cert_no' => '100', 'crew' => 10, 'spinnakers' => 4], $raceId);
        Env::addRating('irc', 'MOJITO - SEC', 'GBR4822R', 1.052, 1.040, ['cert_no' => '200', 'crew' => 4, 'spinnakers' => 2, 'secondary' => 'SEC'], $raceId);

        if (!$raceId) {
            Env::submit(Env::boat(['Boat_Name' => 'Mojito', 'Sail_Number' => 'GBR 4822R']));
        }
    }

    public function testBoatIsOnItsPrimaryCertificateUnlessItChoosesOtherwise(): void
    {
        $this->boatWithTwoCertificates();
        $entry = $this->entries()[0];

        $this->assertSame('1.084', $entry->rating_text);
        $this->assertSame('primary', $entry->certificate);
        $this->assertSame('primary', $entry->certificate_wanted);
        $this->assertTrue($entry->has_secondary, 'The list has a secondary certificate for it');
        $this->assertSame('ok', $entry->rating_status);
        $this->assertSame('100', $entry->cert_no);
    }

    public function testChoosingTheSecondaryCertificateUsesItsRatingAndDetails(): void
    {
        $this->boatWithTwoCertificates();
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');
        $entry = $this->entries()[0];

        $this->assertSame('1.052', $entry->rating_text);
        $this->assertNumber(1.052, $entry->rating);
        $this->assertSame('secondary', $entry->certificate);
        $this->assertSame('secondary', $entry->certificate_wanted);
        $this->assertSame('200', $entry->cert_no, 'Boat data is looked up by the secondary certificate number');
        $this->assertSame(['4', '2'], [$entry->crew, $entry->spinnakers], 'Details come from the secondary certificate');
        $this->assertSame('ok', $entry->rating_status, 'The boat is still firmly identified');
        $this->assertSame('', $entry->rating_note);
        $this->assertSame('Pat Office', $entry->certificate_set_by);
        $this->assertTrue($entry->certificate_set_at !== '');
    }

    public function testChoosingPrimaryAgainGoesBack(): void
    {
        $this->boatWithTwoCertificates();
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'primary', 'Pat Office');
        $entry = $this->entries()[0];

        $this->assertSame('1.084', $entry->rating_text);
        $this->assertSame('primary', $entry->certificate);
        $this->assertSame('', $entry->certificate_set_at);
        $this->assertCount(0, Env::rows('#__isorairc_cert_choices'), 'Nothing is kept for a boat on its primary certificate');
    }

    public function testChoosingTwiceKeepsOneRecord(): void
    {
        $this->boatWithTwoCertificates();
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'First');
        EntriesHelper::setCertificate($this->race, 'MOJITO', 'gbr4822r', 'secondary', 'Second');

        $this->assertCount(1, Env::rows('#__isorairc_cert_choices'));
        $this->assertSame('Second', $this->entries()[0]->certificate_set_by);
    }

    public function testTheChoiceIsForOneRaceOnly(): void
    {
        $second = Env::addRace('Race 2', '2027-06-12', '2027-06-05 22:59:00', 'Race_2');
        Env::addRating('irc', 'MOJITO', 'GBR4822R', 1.084);
        Env::addRating('irc', 'MOJITO - SEC', 'GBR4822R', 1.052, null, ['secondary' => 'SEC']);
        Env::submit(Env::boat(['Boat_Name' => 'Mojito', 'Sail_Number' => 'GBR 4822R', 'Race_2' => 'Yes']));
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');

        $this->assertSame('1.052', $this->entries()[0]->rating_text, 'Race 1');
        $this->assertSame('1.084', $this->entries($second)[0]->rating_text, 'Race 2 is untouched');
    }

    public function testTheChoiceIsForOneBoatOnly(): void
    {
        $this->boatWithTwoCertificates();
        Env::addRating('irc', 'OTHER', 'IRL1', 1.000);
        Env::addRating('irc', 'OTHER - SEC', 'IRL1', 0.950, null, ['secondary' => 'SEC']);
        Env::submit(Env::boat(['Boat_Name' => 'Other', 'Sail_Number' => 'IRL 1']));
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');
        $entries = $this->entries();

        $this->assertSame(['Mojito' => '1.052', 'Other' => '1.000'], array_column($entries, 'rating_text', 'boat'));
    }

    public function testTheChoiceSurvivesTheBoatSendingItsFormAgain(): void
    {
        $this->boatWithTwoCertificates();
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');
        Env::submit(Env::boat(['Boat_Name' => 'MOJITO', 'Sail_Number' => 'GBR4822R']), '2026-11-01 09:00:00');

        $this->assertSame('1.052', $this->entries()[0]->rating_text);
    }

    public function testSecondaryCertificateIsUsedFromTheFrozenListAfterTheDeadline(): void
    {
        $this->boatWithTwoCertificates();
        $this->boatWithTwoCertificates($this->race);
        $db = Env::db();
        $db->setQuery('UPDATE ' . $db->quoteName('#__isorairc_ratings') . ' SET ' . $db->quoteName('tcc') . ' = ' . $db->quoteName('tcc') . ' + 0.1')->execute();
        Env::freeze($this->race, 'irc');

        $this->assertSame('1.084', $this->entries()[0]->rating_text, 'Frozen primary');

        // Race management can still swap after the deadline; the frozen secondary rating is used.
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');
        $this->assertSame('1.052', $this->entries()[0]->rating_text, 'Frozen secondary');
    }

    public function testNonSpinnakerClassUsesTheSecondaryCertificatesNonSpinnakerRating(): void
    {
        Env::addRating('irc', 'MOJITO', 'GBR4822R', 1.084, 1.060);
        Env::addRating('irc', 'MOJITO - SEC', 'GBR4822R', 1.052, 1.040, ['secondary' => 'SEC']);
        Env::submit(Env::boat(['Boat_Name' => 'Mojito', 'Sail_Number' => 'GBR 4822R', 'Class_Racing' => 'IRC Non-Spinnaker']));
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');

        $this->assertSame('1.040', $this->entries()[0]->rating_text);
    }

    public function testBoatWithoutASecondaryCertificate(): void
    {
        Env::addRating('irc', 'SEA BREEZE', 'IRL1234', 1.045);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));
        $entry = $this->entries()[0];

        $this->assertFalse($entry->has_secondary);
        $this->assertSame('primary', $entry->certificate);

        // Chosen all the same (its secondary certificate may have lapsed since): the primary is used and flagged.
        EntriesHelper::setCertificate($this->race, 'Sea Breeze', 'IRL 1234', 'secondary', 'Pat Office');
        $entry = $this->entries()[0];

        $this->assertSame('1.045', $entry->rating_text);
        $this->assertSame('primary', $entry->certificate);
        $this->assertSame('secondary', $entry->certificate_wanted);
        $this->assertSame('check', $entry->rating_status);
        $this->assertSame('Secondary certificate chosen, but the list has none for this boat', $entry->rating_note);
    }

    public function testSecondaryCertificateIsFoundByNameWhenTheSailNumberWasMistyped(): void
    {
        Env::addRating('irc', 'MOJITO', 'GBR4822R', 1.084);
        Env::addRating('irc', 'MOJITO - SEC', 'GBR4822R', 1.052, null, ['secondary' => 'SEC']);
        Env::submit(Env::boat(['Boat_Name' => 'Mojito', 'Sail_Number' => 'GBR 9999']));
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 9999', 'secondary', 'Pat Office');
        $entry = $this->entries()[0];

        $this->assertTrue($entry->has_secondary);
        $this->assertSame('1.052', $entry->rating_text);
        $this->assertSame('check', $entry->rating_status, 'Still flagged: the sail number does not match');
    }

    public function testAnotherBoatsSecondaryCertificateIsNotBorrowed(): void
    {
        Env::addRating('irc', 'SEA BREEZE', 'IRL1234', 1.045);
        Env::addRating('irc', 'MOJITO', 'GBR4822R', 1.084);
        Env::addRating('irc', 'MOJITO - SEC', 'GBR4822R', 1.052, null, ['secondary' => 'SEC']);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));
        Env::submit(Env::boat(['Boat_Name' => 'Mojito', 'Sail_Number' => 'GBR 4822R']));
        $entries = array_column($this->entries(), null, 'boat');

        $this->assertFalse($entries['Sea Breeze']->has_secondary);
        $this->assertTrue($entries['Mojito']->has_secondary);
    }

    public function testYtcBoatHasNoCertificateChoice(): void
    {
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845);
        Env::submit(Env::boat(['Boat_Name' => 'Slow Coach', 'Sail_Number' => 'GBR 55', 'Class_Racing' => 'YTC']));
        $entry = $this->entries()[0];

        $this->assertSame('', $entry->certificate);
        $this->assertFalse($entry->has_secondary);
    }

    public function testOnlyPrimaryAndSecondaryCanBeChosen(): void
    {
        $this->assertThrows(function () {
            EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'tertiary', 'Pat Office');
        }, 'primary or secondary');
    }

    public function testDeletingARaceForgetsItsCertificateChoices(): void
    {
        $second = Env::addRace('Race 2', '2027-06-12', '2027-06-05 22:59:00', 'Race_2');
        EntriesHelper::setCertificate($this->race, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');
        EntriesHelper::setCertificate($second, 'Mojito', 'GBR 4822R', 'secondary', 'Pat Office');
        EntriesHelper::deleteCertificateChoices([$this->race]);

        $this->assertSame([$second], array_map('intval', array_column(Env::rows('#__isorairc_cert_choices'), 'race_id')));
    }

    public function testBoatUsingItsOwnTrackerHasItsSerialNumber(): void
    {
        Env::submit(Env::boat(['OwnTracker' => 'Yes', 'TrackerSerialNumber' => ' 0412345 ']));
        $entry = $this->entries()[0];

        $this->assertSame('Yes', $entry->owntracker);
        $this->assertSame('0412345', $entry->tracker);
    }

    public function testSerialNumberIsIgnoredUnlessTheBoatSaidItUsesItsOwnTracker(): void
    {
        // The serial box is hidden when "No" is chosen, but may still hold what was typed before.
        Env::submit(Env::boat(['Boat_Name' => 'Said No', 'Sail_Number' => '1', 'OwnTracker' => 'No', 'TrackerSerialNumber' => '111']));
        Env::submit(Env::boat(['Boat_Name' => 'No Answer', 'Sail_Number' => '2', 'OwnTracker' => '', 'TrackerSerialNumber' => '222']));
        Env::submit(Env::boat(['Boat_Name' => 'Yes No Serial', 'Sail_Number' => '3', 'OwnTracker' => 'yes', 'TrackerSerialNumber' => '']));
        Env::submit(Env::boat(['Boat_Name' => 'Yes Serial', 'Sail_Number' => '4', 'OwnTracker' => 'YES', 'TrackerSerialNumber' => '444']));

        $this->assertSame(
            ['No Answer' => '', 'Said No' => '', 'Yes No Serial' => '', 'Yes Serial' => '444'],
            array_column($this->entries(), 'tracker', 'boat')
        );
    }

    public function testLatestFormDecidesTheTracker(): void
    {
        Env::submit(Env::boat(['OwnTracker' => 'Yes', 'TrackerSerialNumber' => '111']), '2026-10-01 10:00:00');
        Env::submit(Env::boat(['OwnTracker' => 'No', 'TrackerSerialNumber' => '']), '2026-10-02 10:00:00');

        $this->assertSame('', $this->entries()[0]->tracker);
    }

    public function testTrackerFieldNamesCanBeChangedInTheOptions(): void
    {
        Env::setParams(['entry_owntracker_field' => 'Own_YB', 'entry_tracker_field' => 'YB_Serial']);
        Env::submit(Env::boat(['Own_YB' => 'Yes', 'YB_Serial' => '555', 'OwnTracker' => 'Yes', 'TrackerSerialNumber' => '999']));

        $this->assertSame('555', $this->entries()[0]->tracker);
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

    // ------------------------------------------------------------ one entry form per season (1.9.0)

    public function testRaceReadsTheEntryFormStoredWithIt(): void
    {
        $lastYear = Env::addRace('Last year, race 1', '2026-05-02', '2026-04-25 22:59:00', 'Race_1', 7, 12);
        Env::submit(Env::boat(['Boat_Name' => 'Old Salt', 'Sail_Number' => '1']), '2025-10-01 10:00:00', 12);
        Env::submit(Env::boat(['Boat_Name' => 'Newcomer', 'Sail_Number' => '2']));

        $this->assertSame(['Old Salt'], $this->names($lastYear));
        $this->assertSame(['Newcomer'], $this->names(), 'A race without a stored form reads the form in the options');
        $this->assertSame(12, EntriesHelper::raceFormId(Env::race($lastYear)));
        $this->assertSame(Env::FORM_ID, EntriesHelper::raceFormId(Env::race($this->race)));
    }

    public function testSameQuestionOnNextSeasonsFormDoesNotChangeLastSeasonsList(): void
    {
        // The form was copied for the new season, so both forms have a question called Race_1.
        $lastYear = Env::addRace('Last year, race 1', '2026-05-02', '2026-04-25 22:59:00', 'Race_1', 7, 12);
        $thisYear = Env::addRace('This year, race 1', '2027-05-01', '2027-04-24 22:59:00', 'Race_1', 7, Env::FORM_ID);

        Env::submit(Env::boat(['Boat_Name' => 'Both Years', 'Sail_Number' => '1']), '2025-10-01 10:00:00', 12);
        Env::submit(Env::boat(['Boat_Name' => 'Both Years', 'Sail_Number' => '1']), '2026-10-01 10:00:00');
        Env::submit(Env::boat(['Boat_Name' => 'Stayed Home', 'Sail_Number' => '2']), '2025-10-01 10:00:00', 12);
        Env::submit(Env::boat(['Boat_Name' => 'Stayed Home', 'Sail_Number' => '2', 'Race_1' => 'No']), '2026-10-01 10:00:00');

        $this->assertSame(['Both Years', 'Stayed Home'], $this->names($lastYear), "This year's No does not withdraw the boat from last year's race");
        $this->assertSame(['Both Years'], $this->names($thisYear), "Last year's Yes does not enter the boat this year");
    }

    public function testChoosingNextSeasonsFormInTheOptionsLeavesLinkedRacesAlone(): void
    {
        $linked = Env::addRace('Linked', '2027-05-08', '2027-05-01 22:59:00', 'Race_1', 7, Env::FORM_ID);
        Env::submit(Env::boat(['Boat_Name' => 'This Season']));
        Env::submit(Env::boat(['Boat_Name' => 'Next Season']), '2027-10-01 10:00:00', 14);
        Env::setParams(['entry_form_id' => 14]);

        $this->assertSame(['This Season'], $this->names($linked));
        $this->assertSame(['Next Season'], $this->names(), 'A race without a stored form follows the options');
    }

    public function testEmptyFormInTheOptionsSwitchesOffLinkedRacesToo(): void
    {
        $linked = Env::addRace('Linked', '2027-05-08', '2027-05-01 22:59:00', 'Race_1', 7, Env::FORM_ID);
        Env::submit(Env::boat());
        Env::setParams(['entry_form_id' => 0]);

        $this->assertSame(0, EntriesHelper::raceFormId(Env::race($linked)));
        $this->assertSame([], $this->names($linked));
    }

    public function testRaceQuestionsCanBeReadForAGivenForm(): void
    {
        Env::question('Race_Old', 'Last year', 'radioGroup', "Yes\nNo", 12);

        $this->assertSame(['Race_Old' => 'Last year'], EntriesHelper::raceFields(12));
        $this->assertSame(['Race_1', 'Race_2'], array_keys(EntriesHelper::raceFields(0)), 'No form given: the one in the options');
    }

    // ------------------------------------------------------------ one boat's rating, and filling in the entry form (1.9.0)

    public function testOneBoatCanBeLookedUpOnTheCurrentList(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, 1.021, ['issue_date' => '2026-03-15', 'crew' => 9, 'cert_no' => '555']);
        $found = EntriesHelper::lookup(' sea breeze ', 'irl 1234');

        $this->assertSame(['irc', 'ok', '1.045', '2026-03-15', '9', '555', 'primary'], [
            $found->scheme, $found->rating_status, $found->rating_text, $found->issue_date, $found->crew, $found->cert_no, $found->certificate,
        ]);
        $this->assertSame('1.021', EntriesHelper::lookup('Sea Breeze', 'IRL1234', 'IRC - Non Spinnaker (for the full season)')->rating_text, 'Non-spinnaker class');
    }

    public function testLookupSaysHowWellTheBoatMatched(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        Env::addRating('ytc', 'Little Wing', 'IRL77', 0.912);

        $this->assertSame('check', EntriesHelper::lookup('Sea Breeze', 'IRL 9999')->rating_status, 'Name only');
        $this->assertSame('check', EntriesHelper::lookup('Sea Breese', 'IRL 1234')->rating_status, 'Sail number only');

        $missing = EntriesHelper::lookup('Nobody', 'IRL 1');
        $this->assertSame(['none', null, '', 'Not found in the IRC list'], [$missing->rating_status, $missing->rating, $missing->rating_text, $missing->rating_note]);

        $ytc = EntriesHelper::lookup('Little Wing', 'IRL 77', 'YTC- For Coastal Races only');
        $this->assertSame(['ytc', 'ok', ''], [$ytc->scheme, $ytc->rating_status, $ytc->issue_date]);
        $this->assertSame('none', EntriesHelper::lookup('Little Wing', 'IRL 77', 'IRC')->rating_status, 'A YTC boat is not on the IRC list');
    }

    public function testLookupUsesThePrimaryCertificateAndIgnoresAnyRacesChoiceOrFrozenList(): void
    {
        $this->boatWithTwoCertificates();
        $boat = $this->entries()[0];
        EntriesHelper::setCertificate($this->race, $boat->boat, $boat->sail, 'secondary', 'test');
        Env::addRating('irc', $boat->boat, str_replace(' ', '', $boat->sail), 0.900, null, [], $this->race);
        Env::freeze($this->race, 'irc');

        $found = EntriesHelper::lookup($boat->boat, $boat->sail);

        $this->assertSame(['1.084', 'primary'], [$found->rating_text, $found->certificate]);
        $this->assertTrue($found->has_secondary);
    }

    public function testEntryFormIsFilledInFromTheIrcList(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, null, ['issue_date' => '2026-03-05']);
        $form  = Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234', 'Latest_IRC_TCC' => '1.039', 'IRCCertIssueDate' => 'typed by somebody']);
        $found = EntriesHelper::fillFromIrcList($form);

        $this->assertSame('1.045', $form['Latest_IRC_TCC'], 'The rating on the list replaces the one typed');
        $this->assertSame('5 Mar 2026', $form['IRCCertIssueDate']);
        $this->assertSame('ok', $found->rating_status);
        $this->assertSame('Pat Skipper', $form['FullName'], 'Nothing else is touched');
        $this->assertCount(\count(Env::boat()) + 1, $form);
    }

    public function testEntryFormKeepsTheTypedRatingWhenTheBoatIsNotSurelyOnTheList(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, null, ['issue_date' => '2026-03-05']);
        Env::addRating('ytc', 'Little Wing', 'IRL77', 0.912);

        $cases = [
            'Not on the list'       => ['Boat_Name' => 'Nobody', 'Sail_Number' => 'IRL 1'],
            'Only the name matches' => ['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 9999'],
            'Only the sail matches' => ['Boat_Name' => 'Sea Breese', 'Sail_Number' => 'IRL 1234'],
            'A YTC class'           => ['Boat_Name' => 'Little Wing', 'Sail_Number' => 'IRL 77', 'Class_Racing' => 'YTC- For Coastal Races only'],
            'No sail number'        => ['Boat_Name' => 'Sea Breeze', 'Sail_Number' => ''],
        ];

        foreach ($cases as $what => $answers) {
            $form = Env::boat($answers + ['Latest_IRC_TCC' => '1.039', 'IRCCertIssueDate' => 'typed by somebody']);
            EntriesHelper::fillFromIrcList($form);

            $this->assertSame('1.039', $form['Latest_IRC_TCC'], $what . ': the rating stays as typed');
            $this->assertSame('', $form['IRCCertIssueDate'], $what . ': no issue date');
        }
    }

    public function testEntryFormFillUsesTheNonSpinnakerRatingForANonSpinnakerClass(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, 1.021, ['issue_date' => '2026-03-05']);
        // RSForm sends a question with choices as a list.
        $form = Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234', 'Class_Racing' => ['IRC - Non Spinnaker (for the full season)']]);
        EntriesHelper::fillFromIrcList($form);

        $this->assertSame('1.021', $form['Latest_IRC_TCC']);
    }

    public function testEntryFormFillCanBeToldWhichFieldsToUse(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, null, ['issue_date' => '2026-03-05']);
        $boat = Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234', 'Latest_IRC_TCC' => '1.039']);

        $form = $boat;
        EntriesHelper::fillFromIrcList($form, ['issue_date' => 'Cert_Date']);
        $this->assertSame(['1.039', '5 Mar 2026'], [$form['Latest_IRC_TCC'], $form['Cert_Date']], 'Only the date');

        $form = $boat;
        EntriesHelper::fillFromIrcList($form, ['rating' => 'List_TCC', 'issue_date' => '']);
        $this->assertSame(['1.039', '1.045'], [$form['Latest_IRC_TCC'], $form['List_TCC']], 'The rating in a field of its own');
        $this->assertFalse(isset($form['IRCCertIssueDate']));

        // The boat's own fields are the ones named in the options.
        Env::setParams(['entry_boat_field' => 'Yacht', 'entry_sail_field' => 'Sail']);
        $form = ['Yacht' => 'Sea Breeze', 'Sail' => 'IRL 1234', 'Class_Racing' => 'IRC'];
        EntriesHelper::fillFromIrcList($form);
        $this->assertSame('1.045', $form['Latest_IRC_TCC']);
    }

    public function testEntryFormFillNeverStopsTheForm(): void
    {
        $nothing = null;
        $this->assertTrue(EntriesHelper::fillFromIrcList($nothing) === null, 'No answers at all');

        Env::setParams(['irc_enabled' => 0]);
        $form = Env::boat(['Latest_IRC_TCC' => '1.039']);
        EntriesHelper::fillFromIrcList($form);
        $this->assertSame('1.039', $form['Latest_IRC_TCC'], 'IRC switched off');
    }
}
