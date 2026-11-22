<?php

/**
 * A stand-in web browser: fetches pages from the test site, keeps cookies
 * between requests and can sign in, so pages are tested as a visitor sees them.
 */

namespace IsoraTests;

class Response
{
    public int $status = 0;

    public string $body = '';

    /** Final address after any redirects. */
    public string $url = '';

    /** Headers of the final response, names in lower case. */
    public array $headers = [];

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /** The page's text with tags removed and white space collapsed. */
    public function text(): string
    {
        $body = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $this->body);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** Only the part of the page written by the extension, without the site's menus and modules. */
    public function component(string $class): string
    {
        $at = strpos($this->body, 'class="' . $class . '"');

        if ($at === false) {
            return '';
        }

        $end = strpos($this->body, '</main>', $at);

        return substr($this->body, $at, $end === false ? null : $end - $at);
    }
}

class Browser
{
    private string $jar;

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'isora-cookies');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function get(string $path, bool $follow = true): Response
    {
        return $this->request($path, null, $follow);
    }

    public function post(string $path, array $fields, bool $follow = true): Response
    {
        return $this->request($path, $fields, $follow);
    }

    /** The anti-forgery token Joomla puts in every form on a page. */
    public function token(string $html): string
    {
        if (!preg_match('/name="([a-f0-9]{32})" value="1"/', $html, $match)) {
            throw new \RuntimeException('No form token on the page.');
        }

        return $match[1];
    }

    /** Sign in on the public site. */
    public function loginSite(string $username, string $password): void
    {
        $page = $this->get('/index.php?option=com_users&view=login');
        $this->post('/index.php?option=com_users&task=user.login', [
            'username' => $username, 'password' => $password, 'return' => base64_encode('index.php'), $this->token($page->body) => 1,
        ]);

        $check = $this->get('/index.php?option=com_users&view=profile');

        if (strpos($check->url, 'view=login') !== false || strpos($check->body, 'name="password"') !== false) {
            throw new \RuntimeException('Signing in on the site as ' . $username . ' did not work.');
        }
    }

    /** Sign in to the administrator area. */
    public function loginAdmin(string $username, string $password): void
    {
        $page = $this->get('/administrator/index.php');
        $home = $this->post('/administrator/index.php', [
            'username' => $username, 'passwd' => $password, 'option' => 'com_login', 'task' => 'login',
            'return' => base64_encode('index.php'), $this->token($page->body) => 1,
        ]);

        if (strpos($home->body, 'name="passwd"') !== false) {
            throw new \RuntimeException('Signing in to the administrator area as ' . $username . ' did not work.');
        }
    }

    private function request(string $path, ?array $fields, bool $follow): Response
    {
        $response = new Response();
        $curl     = curl_init(preg_match('#^https?://#', $path) ? $path : Env::$siteUrl . $path);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS      => 8,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HEADERFUNCTION => static function ($curl, $line) use ($response) {
                if (strncmp($line, 'HTTP/', 5) === 0) {
                    $response->headers = [];
                } elseif (strpos($line, ':') !== false) {
                    [$name, $value] = explode(':', $line, 2);
                    $response->headers[strtolower(trim($name))] = trim($value);
                }

                return \strlen($line);
            },
        ]);

        if ($fields !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        $body = curl_exec($curl);

        if ($body === false) {
            throw new \RuntimeException('Could not reach the test site: ' . curl_error($curl));
        }

        $response->body   = $body;
        $response->status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $response->url    = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        curl_close($curl);

        return $response;
    }
}
