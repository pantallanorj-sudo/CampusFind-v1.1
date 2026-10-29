<?php
/**
 * Tiny HTTP client for the tests. Keeps cookies like a browser and does NOT follow
 * redirects automatically, so tests can look at the redirect itself.
 * Uses only PHP streams (no curl extension needed).
 */
declare(strict_types=1);

final class HttpClient
{
    public ?string $home = null;          // a page that contains a CSRF token for this user
    private string $base;
    private array $cookies = [];

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function setCookie(string $name, string $value): void
    {
        $this->cookies[$name] = $value;
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** POST form fields. Pass $files = ['photo' => ['name' => 'a.png', 'type' => 'image/png', 'content' => '...']] for uploads. */
    public function post(string $path, array $data = [], array $files = []): array
    {
        if (!$files) {
            return $this->request('POST', $path, http_build_query($data), ['Content-Type: application/x-www-form-urlencoded']);
        }
        $boundary = '----CampusFindTest' . bin2hex(random_bytes(8));
        $body = '';
        foreach ($data as $name => $value) {
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        foreach ($files as $field => $file) {
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$field\"; filename=\"{$file['name']}\"\r\n"
                   . "Content-Type: {$file['type']}\r\n\r\n{$file['content']}\r\n";
        }
        $body .= "--$boundary--\r\n";
        return $this->request('POST', $path, $body, ["Content-Type: multipart/form-data; boundary=$boundary"]);
    }

    /** Follow the redirect in a response (one hop). */
    public function follow(array $response): array
    {
        return $this->get(self::locationPath($response));
    }

    /** The Location header reduced to "/path?query". */
    public static function locationPath(array $response): string
    {
        $loc = (string) ($response['location'] ?? '');
        $parts = parse_url($loc);
        $path = ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        return $path;
    }

    private function request(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) {
                $pairs[] = $k . '=' . $v;
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }
        $options = [
            'method'          => $method,
            'header'          => implode("\r\n", $headers),
            'ignore_errors'   => true,
            'follow_location' => 0,
            'max_redirects'   => 0,
            'timeout'         => 30,
        ];
        if ($body !== null) {
            $options['content'] = $body;
        }
        $context = stream_context_create(['http' => $options]);
        $content = @file_get_contents($this->base . $path, false, $context);
        $raw = $http_response_header ?? [];

        $status = 0;
        $outHeaders = [];
        foreach ($raw as $i => $line) {
            if ($i === 0 || str_starts_with($line, 'HTTP/')) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name === 'set-cookie') {
                $this->storeCookie($value);
            }
            $outHeaders[$name] = $value;
        }

        return [
            'status'   => $status,
            'body'     => $content === false ? '' : $content,
            'headers'  => $outHeaders,
            'location' => $outHeaders['location'] ?? null,
        ];
    }

    private function storeCookie(string $header): void
    {
        $parts = array_map('trim', explode(';', $header));
        $first = array_shift($parts);
        if ($first === null || !str_contains($first, '=')) {
            return;
        }
        [$name, $value] = explode('=', $first, 2);
        $expired = false;
        foreach ($parts as $attr) {
            $lower = strtolower($attr);
            if ($lower === 'max-age=0' || str_starts_with($lower, 'max-age=-')) {
                $expired = true;
            }
            if (str_starts_with($lower, 'expires=')) {
                $ts = strtotime(substr($attr, 8));
                if ($ts !== false && $ts < time()) {
                    $expired = true;
                }
            }
        }
        if ($expired || $value === '' || $value === 'deleted') {
            unset($this->cookies[$name]);
        } else {
            $this->cookies[$name] = $value;
        }
    }
}
