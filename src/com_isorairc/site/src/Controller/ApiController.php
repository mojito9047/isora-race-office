<?php

namespace Isora\Component\Isorairc\Site\Controller;

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Read-only JSON feed of the races and the boats entered for them, for
 * another system such as Crewmanager. Every request must carry the key set
 * in the options (API tab); with no key set the feed is switched off.
 *
 *   index.php?option=com_isorairc&task=api.races&format=json
 *   index.php?option=com_isorairc&task=api.races&entries=1&format=json
 *   index.php?option=com_isorairc&task=api.entries&race=7&format=json
 *
 * The key is sent in an "X-Api-Key" header, or as "Authorization: Bearer <key>".
 */
class ApiController extends BaseController
{
    /** A shorter key is too easy to guess, so it does not switch the feed on. */
    public const MIN_KEY_LENGTH = 20;

    /**
     * The races linked to the entry form, in date order. With entries=1 each
     * race also lists its boats.
     */
    public function races()
    {
        $this->authorise();

        $withEntries = $this->input->getInt('entries') === 1;
        $races       = [];

        foreach (EntriesHelper::races() as $race) {
            $item = $this->race($race);

            if ($withEntries) {
                $item['entries'] = $this->boats($race);
            }

            $races[] = $item;
        }

        $this->send(200, ['races' => $races]);
    }

    /** One race and the boats entered for it. */
    public function entries()
    {
        $this->authorise();

        $races  = EntriesHelper::races();
        $raceId = $this->input->getInt('race');

        if (!isset($races[$raceId])) {
            $this->send(404, ['error' => 'Race not found.']);
        }

        $this->send(200, ['race' => $this->race($races[$raceId]), 'entries' => $this->boats($races[$raceId])]);
    }

    /** @return array<string, mixed> */
    private function race(object $race): array
    {
        return [
            'id'              => (int) $race->id,
            'name'            => (string) $race->name,
            'date'            => substr((string) $race->race_date, 0, 10),
            'rating_deadline' => $this->time((string) $race->rating_deadline),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function boats(object $race): array
    {
        $boats = [];

        foreach (EntriesHelper::entries($race) as $entry) {
            $boats[] = [
                'boat'             => $entry->boat,
                'sail_number'      => $entry->sail,
                'type'             => $entry->model,
                'class'            => $entry->class,
                'club'             => $entry->club,
                'rating_system'    => strtoupper($entry->scheme),
                'rating'           => $entry->rating,
                // False when the boat was matched on its name or its sail number only.
                'rating_confirmed' => $entry->rating_status === 'ok',
                'person_in_charge' => [
                    'name'  => $entry->contact,
                    'email' => $entry->email,
                    'phone' => $entry->phone,
                ],
                'entered_at'       => $this->time((string) $entry->submitted),
            ];
        }

        return $boats;
    }

    /** A date and time stored in UTC, as ISO 8601. */
    private function time(string $utc): ?string
    {
        $stamp = $utc !== '' ? strtotime($utc . ' UTC') : false;

        return $stamp ? gmdate('Y-m-d\TH:i:s\Z', $stamp) : null;
    }

    /** Stop unless the request carries the key from the options. */
    private function authorise(): void
    {
        $key = trim((string) ComponentHelper::getParams('com_isorairc')->get('api_key', ''));

        if (\strlen($key) < self::MIN_KEY_LENGTH) {
            $this->send(404, ['error' => 'The API is switched off.']);
        }

        $server = $this->input->server;
        $given  = trim((string) $server->getString('HTTP_X_API_KEY', ''));

        if ($given === '') {
            $header = (string) ($server->getString('HTTP_AUTHORIZATION', '') ?: $server->getString('REDIRECT_HTTP_AUTHORIZATION', ''));

            if (stripos($header, 'Bearer ') === 0) {
                $given = trim(substr($header, 7));
            }
        }

        if ($given === '' || !hash_equals($key, $given)) {
            $this->send(401, ['error' => 'A valid API key is required.']);
        }
    }

    /**
     * Send the answer and stop.
     *
     * @param array<string, mixed> $data
     *
     * @return never
     */
    private function send(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->app->close();
    }
}
