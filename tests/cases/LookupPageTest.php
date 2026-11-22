<?php

namespace IsoraTests\Cases;

use IsoraTests\Browser;
use IsoraTests\Env;
use IsoraTests\Response;
use IsoraTests\SiteTest;

/**
 * The public rating lookup page, fetched as a visitor's browser would.
 */
class LookupPageTest extends SiteTest
{
    /** The page's address: its menu item, as set up in the admin guide. */
    private const PAGE = '/rating-lookup';

    private function search(string $term, int $race = 0): Response
    {
        $page = (new Browser())->get(self::PAGE . '?q=' . rawurlencode($term) . ($race ? '&race=' . $race : ''));
        $this->assertSame(200, $page->status, 'Page status');
        $this->assertNoPhpErrors($page);

        return $page;
    }

    /** The text of the part of the page the extension writes. */
    private function found(string $term, int $race = 0): string
    {
        $page = $this->search($term, $race);
        $part = new Response();
        $part->body = $page->component('com-isorairc-ratings');
        $this->assertTrue($part->body !== '', 'The lookup is on the page');

        return $part->text();
    }

    public function testPageShowsTheSearchBox(): void
    {
        $page = (new Browser())->get(self::PAGE);

        $this->assertSame(200, $page->status);
        $this->assertNoPhpErrors($page);
        $this->assertContains('Boat name or sail number', $page->body);
        $this->assertNotContains('<table', $page->component('com-isorairc-ratings'), 'No results before a search');
    }

    public function testFindsABoatByPartOfItsName(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, 1.021, ['cert_no' => '4411', 'issue_date' => '2026-03-15']);
        Env::addRating('irc', 'Other Boat', 'GBR1', 0.999);
        $text = $this->found('bree');

        $this->assertContains('Sea Breeze IRL1234 1.045 1.021 4411 15 Mar 2026', $text);
        $this->assertNotContains('Other Boat', $text);
    }

    public function testFindsABoatBySailNumberWithOrWithoutSpaces(): void
    {
        Env::addRating('irc', 'Packed', 'IRL1234', 1.000);
        Env::addRating('irc', 'Spaced', 'GBR 5678 L', 1.000);

        $this->assertContains('Packed', $this->found('IRL 1234'));
        $this->assertContains('Packed', $this->found('irl1234'));
        $this->assertContains('Spaced', $this->found('gbr5678l'));
        $this->assertContains('Spaced', $this->found('5678'));
    }

    public function testShowsBothRatingSystems(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        Env::addRating('ytc', 'Sea Breeze', 'IRL1234', 845, 820, ['boat_type' => 'Sigma 33', 'club' => 'Holyhead SC']);
        $text = $this->found('Sea Breeze');

        $this->assertContains('IRC', $text);
        $this->assertContains('Sea Breeze IRL1234 Sigma 33 845 820 Holyhead SC', $text, 'YTC row, in whole numbers');
    }

    public function testSaysSoWhenNothingMatches(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        $text = $this->found('zzzz');

        $this->assertContains('No boats found matching “zzzz”', $text);
    }

    public function testASingleLetterIsNotSearched(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);

        $this->assertNotContains('Sea Breeze', $this->found('e'));
    }

    public function testPercentSignDoesNotListEveryBoat(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);

        $this->assertNotContains('Sea Breeze', $this->found('%%'));
        $this->assertNotContains('Sea Breeze', $this->found('__'));
    }

    public function testSearchTextIsShownSafely(): void
    {
        $page = $this->search('<script>alert(1)</script>');

        $this->assertNotContains('<script>alert(1)</script>', $page->body);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $page->body);
    }

    public function testQuoteInTheSearchDoesNotCauseAnError(): void
    {
        Env::addRating('irc', "Finn's Folly", 'IRL2', 1.000);

        $this->assertContains("Finn's Folly", $this->found("finn's"));
        $this->assertContains('No boats found', $this->found("' OR '1'='1"));
    }

    public function testBoatNamesFromTheListsAreShownSafely(): void
    {
        Env::addRating('irc', '<b>Bold</b> Move', 'IRL3', 1.000);
        $page = $this->search('Bold');

        $this->assertContains('&lt;b&gt;Bold&lt;/b&gt; Move', $page->body);
        $this->assertNotContains('<b>Bold</b>', $page->body);
    }

    public function testNotesForEndorsedSecondaryAndProvisional(): void
    {
        Env::addRating('irc', 'Noted One', 'IRL4', 1.000, null, ['endorsed' => 'E', 'secondary' => 'S']);
        Env::addRating('ytc', 'Noted Two', 'IRL5', 800, null, ['provisional' => 'TRUE']);

        $this->assertContains('Endorsed, Secondary certificate', $this->found('Noted One'));
        $this->assertContains('Provisional', $this->found('Noted Two'));
    }

    public function testSaysWhenTheRatingsWereLastUpdated(): void
    {
        Env::import('irc', Env::ircCsv(), '2026-11-03 08:15:00');
        $text = $this->found('Filler 001');

        // 08:15 UTC is 08:15 in Ireland in November.
        $this->assertContains('Current ratings, last updated ' . Env::localDate('2026-11-03 08:15:00', 0, 'd M Y H:i'), $text);
    }

    public function testOnlyTheFirstTwoHundredMatchesAreShown(): void
    {
        for ($i = 1; $i <= 205; $i++) {
            Env::addRating('irc', sprintf('Crowd %03d', $i), 'C' . $i, 1.000);
        }

        $page = $this->search('Crowd');

        $this->assertContains('Showing the first 200 matches', $page->body);
        $this->assertSame(200, substr_count($page->component('com-isorairc-ratings'), '<th scope="row">'), 'Rows shown');
    }

    public function testSwitchedOffSystemIsNotShown(): void
    {
        Env::addRating('ytc', 'Sea Breeze', 'IRL1234', 845);
        Env::setParams(['ytc_enabled' => 0]);
        $text = $this->found('Sea Breeze');

        $this->assertNotContains('YTC', $text);
        $this->assertNotContains('845', $text);
    }

    public function testFrozenRatingsOfARaceCanBeLookedUp(): void
    {
        $race = Env::addRace('Race 1', '2026-05-09', '2026-05-02 22:59:00');
        Env::addRating('irc', 'Mover', 'IRL9', 1.000);
        Env::addRating('irc', 'Mover', 'IRL9', 0.975, null, [], $race);
        Env::freeze($race, 'irc');

        $this->assertContains('Mover IRL9 1.000', $this->found('Mover'), 'Today');

        $text = $this->found('Mover', $race);
        $this->assertContains('Mover IRL9 0.975', $text, 'As at the race');
        $this->assertContains('Ratings frozen at the rating deadline for Race 1 (02 May 2026 23:59)', $text);
        $this->assertContains('No YTC ratings were frozen for this race', $text);
    }

    public function testRacesNotYetFrozenAreNotOffered(): void
    {
        $frozen  = Env::addRace('Frozen race', '2026-05-09', '2026-05-02 22:59:00');
        $pending = Env::addRace('Pending race', '2027-05-09', '2027-05-02 22:59:00');
        Env::freeze($frozen, 'irc');
        Env::addRating('irc', 'Mover', 'IRL9', 1.000);
        $page = $this->search('Mover', $pending);

        $this->assertContains('Frozen race', $page->body);
        $this->assertNotContains('Pending race', $page->body);
        $this->assertContains('Mover IRL9 1.000', $page->text(), 'Asking for an unfrozen race shows today\'s ratings');
    }

    public function testSearchingFromTheMenuItemWorks(): void
    {
        // Version 1.0.2: searching from the page's menu item gave "not found".
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        $browser = new Browser();

        $page = $browser->get('/rating-lookup');
        $this->assertSame(200, $page->status, 'Menu page');
        $this->assertNoPhpErrors($page);
        $this->assertMatches('#<form action="/rating-lookup"#', $page->body, 'The search form posts back to the menu page');

        $result = $browser->get('/rating-lookup?q=breeze&race=0');
        $this->assertSame(200, $result->status, 'Search from the menu page');
        $this->assertContains('Sea Breeze', $result->body);
    }
}
