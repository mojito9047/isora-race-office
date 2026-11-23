<?php

namespace IsoraTests\Cases;

use IsoraTests\Browser;
use IsoraTests\Env;
use IsoraTests\Response;
use IsoraTests\SiteTest;

/**
 * The key-protected feed of races and entered boats that another system
 * (Crewmanager) reads.
 */
class ApiTest extends SiteTest
{
    private const KEY = 'test-key-0123456789-abcdefghij';

    private const RACES = '/index.php?option=com_isorairc&task=api.races&format=json';

    private const ENTRIES = '/index.php?option=com_isorairc&task=api.entries&format=json&race=';

    private const PRIVATE_DETAILS = ['pat.skipper@example.invalid', '555 0100', 'Pat Skipper', 'Sea Breeze'];

    private int $race;

    protected function setUp(): void
    {
        parent::setUp();
        Env::standardForm();
        Env::setParams(['api_key' => self::KEY]);
        $this->race = Env::addRace('Race 1', '2027-05-01', '2027-04-24 22:59:00', 'Race_1');
    }

    private function get(string $path, ?string $key = self::KEY, string $header = 'X-Api-Key: %s'): Response
    {
        return (new Browser())->get($path, true, $key === null ? [] : [sprintf($header, $key)]);
    }

    /** Fetch an address with the key and return the decoded answer. */
    private function json(string $path): array
    {
        $response = $this->get($path);
        $this->assertSame(200, $response->status, 'Status of ' . $path);
        $this->assertNoPhpErrors($response, $path);
        $data = json_decode($response->body, true);
        $this->assertTrue(\is_array($data), 'The answer is JSON: ' . substr($response->body, 0, 200));

        return $data;
    }

    private function twoBoats(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));
        Env::submit(Env::boat(['Boat_Name' => 'Slow Coach', 'Sail_Number' => 'GBR 55', 'Class_Racing' => 'YTC', 'Manufacturer_Model' => 'Sadler 32']), '2026-10-02 08:30:00');
    }

    // ------------------------------------------------------------ the races

    public function testRacesAreListedInDateOrder(): void
    {
        $second = Env::addRace('Race 2', '2027-06-12', '2027-06-05 22:59:00', 'Race_2');
        Env::addRace('Earlier but not on the form', '2027-04-01', '2027-03-25 23:59:00');

        $this->assertSame(
            ['races' => [
                ['id' => $this->race, 'name' => 'Race 1', 'date' => '2027-05-01', 'rating_deadline' => '2027-04-24T22:59:00Z'],
                ['id' => $second, 'name' => 'Race 2', 'date' => '2027-06-12', 'rating_deadline' => '2027-06-05T22:59:00Z'],
            ]],
            $this->json(self::RACES)
        );
    }

    public function testAnswerIsMarkedAsJsonAndNotToBeStored(): void
    {
        $response = $this->get(self::RACES);

        $this->assertContains('application/json', $response->header('content-type'));
        $this->assertContains('no-store', $response->header('cache-control'));
    }

    public function testNoRacesGivesAnEmptyList(): void
    {
        Env::reset();
        Env::setParams(['api_key' => self::KEY]);

        $this->assertSame(['races' => []], $this->json(self::RACES));
    }

    // ------------------------------------------------------------ the boats

    public function testEntriesOfARaceWithEveryDetail(): void
    {
        $this->twoBoats();
        $data = $this->json(self::ENTRIES . $this->race);

        $this->assertSame(['id' => $this->race, 'name' => 'Race 1', 'date' => '2027-05-01', 'rating_deadline' => '2027-04-24T22:59:00Z'], $data['race']);
        $this->assertCount(2, $data['entries']);
        $this->assertSame(
            [
                'boat' => 'Sea Breeze', 'sail_number' => 'IRL 1234', 'type' => 'J/109', 'class' => 'IRC', 'club' => 'Test Yacht Club',
                'rating_system' => 'IRC', 'rating' => 1.045, 'rating_confirmed' => true,
                'person_in_charge' => ['name' => 'Pat Skipper', 'email' => 'pat.skipper@example.invalid', 'phone' => '+353 85 555 0100'],
                'entered_at' => '2026-10-01T10:00:00Z',
            ],
            $data['entries'][0]
        );

        $ytc = $data['entries'][1];
        $this->assertSame('Slow Coach', $ytc['boat']);
        $this->assertSame('YTC', $ytc['rating_system']);
        $this->assertSame(null, $ytc['rating'], 'Not on the YTC list');
        $this->assertSame(false, $ytc['rating_confirmed']);
        $this->assertSame('2026-10-02T08:30:00Z', $ytc['entered_at']);
    }

    public function testOnlyBoatsEnteredForThatRaceAreGiven(): void
    {
        $second = Env::addRace('Race 2', '2027-06-12', '2027-06-05 22:59:00', 'Race_2');
        Env::submit(Env::boat(['Boat_Name' => 'First Only', 'Sail_Number' => '1']));
        Env::submit(Env::boat(['Boat_Name' => 'Both', 'Sail_Number' => '2', 'Race_2' => 'Yes']));
        Env::submit(Env::boat(['Boat_Name' => 'Second Only', 'Sail_Number' => '3', 'Race_1' => 'No', 'Race_2' => 'Yes']));

        $this->assertSame(['Both', 'First Only'], array_column($this->json(self::ENTRIES . $this->race)['entries'], 'boat'));
        $this->assertSame(['Both', 'Second Only'], array_column($this->json(self::ENTRIES . $second)['entries'], 'boat'));
    }

    public function testRatingDoubtIsPassedOn(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL9999', 1.045);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));
        $entry = $this->json(self::ENTRIES . $this->race)['entries'][0];

        $this->assertSame(1.045, $entry['rating']);
        $this->assertSame(false, $entry['rating_confirmed'], 'Name matches but the sail number does not');
    }

    public function testRaceWithoutEntriesGivesAnEmptyList(): void
    {
        $this->assertSame([], $this->json(self::ENTRIES . $this->race)['entries']);
    }

    public function testAllRacesWithTheirBoatsInOneRequest(): void
    {
        $second = Env::addRace('Race 2', '2027-06-12', '2027-06-05 22:59:00', 'Race_2');
        $this->twoBoats();
        Env::submit(Env::boat(['Boat_Name' => 'Both', 'Sail_Number' => '2', 'Race_2' => 'Yes']));
        $races = $this->json(self::RACES . '&entries=1')['races'];

        $this->assertSame([$this->race, $second], array_column($races, 'id'));
        $this->assertSame(['Both', 'Sea Breeze', 'Slow Coach'], array_column($races[0]['entries'], 'boat'));
        $this->assertSame(['Both'], array_column($races[1]['entries'], 'boat'));
        $this->assertFalse(isset($this->json(self::RACES)['races'][0]['entries']), 'Boats are only listed when asked for');
    }

    public function testAccentsAndAwkwardCharactersSurvive(): void
    {
        Env::submit(Env::boat(['Boat_Name' => 'Tír na nÓg "2" <b>&</b> / \\', 'FullName' => "Seán O'Brien"]));
        $entry = $this->json(self::ENTRIES . $this->race)['entries'][0];

        $this->assertSame('Tír na nÓg "2" <b>&</b> / \\', $entry['boat']);
        $this->assertSame("Seán O'Brien", $entry['person_in_charge']['name']);
    }

    public function testUnknownRaceIsNotFound(): void
    {
        $unlinked = Env::addRace('Not on the form', '2027-04-01', '2027-03-25 23:59:00');

        foreach ([999999, $unlinked, 0] as $id) {
            $response = $this->get(self::ENTRIES . $id);
            $this->assertSame(404, $response->status, 'Race ' . $id);
            $this->assertSame(['error' => 'Race not found.'], json_decode($response->body, true));
        }
    }

    // ------------------------------------------------------------ the key

    public function testKeyCanBeSentAsABearerToken(): void
    {
        $response = $this->get(self::RACES, self::KEY, 'Authorization: Bearer %s');

        $this->assertSame(200, $response->status);
        $this->assertCount(1, json_decode($response->body, true)['races']);
    }

    public function testNothingIsGivenWithoutTheRightKey(): void
    {
        $this->twoBoats();
        $attempts = [
            'no key'               => [null, 'X-Api-Key: %s'],
            'wrong key'            => ['wrong-key-0123456789-abcdefghij', 'X-Api-Key: %s'],
            'start of the key'     => [substr(self::KEY, 0, 20), 'X-Api-Key: %s'],
            'key in other letters' => [strtoupper(self::KEY), 'X-Api-Key: %s'],
            'wrong bearer token'   => ['nope', 'Authorization: Bearer %s'],
        ];

        foreach ([self::RACES, self::RACES . '&entries=1', self::ENTRIES . $this->race] as $path) {
            foreach ($attempts as $what => [$key, $header]) {
                $response = $this->get($path, $key, $header);
                $this->assertSame(401, $response->status, $what . ' on ' . $path);
                $this->assertSame(['error' => 'A valid API key is required.'], json_decode($response->body, true), $what);

                foreach (self::PRIVATE_DETAILS as $private) {
                    $this->assertNotContains($private, $response->body, $what);
                }
            }
        }
    }

    public function testKeyInTheAddressIsNotAccepted(): void
    {
        // Addresses end up in server logs, so the key must travel in a header.
        $response = $this->get(self::RACES . '&key=' . self::KEY . '&api_key=' . self::KEY . '&X-Api-Key=' . self::KEY, null);

        $this->assertSame(401, $response->status);
    }

    public function testSignedInRaceManagementStillNeedTheKey(): void
    {
        $browser = new Browser();
        $browser->loginSite(...Env::user('office'));

        $this->assertSame(401, $browser->get(self::ENTRIES . $this->race)->status);
    }

    public function testApiIsOffUntilAKeyIsSet(): void
    {
        $this->twoBoats();

        foreach (['', '   ', 'short-key', str_repeat('k', 19)] as $key) {
            Env::setParams(['api_key' => $key]);

            // Sending the very same value must not get in either.
            $response = $this->get(self::ENTRIES . $this->race, $key === '' ? null : $key);
            $this->assertSame(404, $response->status, 'Key "' . $key . '"');
            $this->assertSame(['error' => 'The API is switched off.'], json_decode($response->body, true));
            $this->assertNotContains('Sea Breeze', $response->body);
        }
    }

    public function testChangingTheKeyCutsOffTheOldOne(): void
    {
        Env::setParams(['api_key' => 'a-new-key-0123456789-abcdefghij']);

        $this->assertSame(401, $this->get(self::RACES)->status, 'Old key');
        $this->assertSame(200, $this->get(self::RACES, 'a-new-key-0123456789-abcdefghij')->status, 'New key');
    }

    public function testKeyFieldIsOnTheOptionsScreen(): void
    {
        $browser = new Browser();
        $browser->loginAdmin(Env::$adminUser, Env::$adminPass);
        $page = $browser->get('/administrator/index.php?option=com_config&view=component&component=com_isorairc');

        $this->assertContains('API key', $page->body);
        $this->assertContains(self::KEY, $page->body, 'The key that was set is shown');
        $browser->get('/administrator/index.php?option=com_config&task=component.cancel&component=com_isorairc');
    }
}
