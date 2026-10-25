<?php
declare(strict_types=1);

namespace VTD\Core;

class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $body;
    private array $headers;

    public function __construct(string $method, string $path, array $queryParams = [], array $body = [], array $headers = [])
    {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->headers = $headers;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Suporte a fallback de query string (ex: api.php?endpoint=suppliers)
        $endpointParam = $_GET['endpoint'] ?? $_GET['action'] ?? null;
        if (!empty($endpointParam)) {
            $path = '/' . ltrim($endpointParam, '/');
        } else {
            // Remove prefixo de script se chamado diretamente (ex: /public/api.php ou /api.php)
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            if (str_starts_with($uri, $scriptName)) {
                $path = substr($uri, strlen($scriptName)) ?: '/';
            } elseif (str_starts_with($uri, '/api')) {
                $path = substr($uri, 4) ?: '/';
            } else {
                $path = $uri;
            }
        }

        $rawBody = file_get_contents('php://input');
        $body = [];
        if (!empty($rawBody)) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        } elseif (!empty($_POST)) {
            $body = $_POST;
        }

        $headers = [];
        foreach ($_SERVER as $key => $val) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string)$val;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return new self($method, $path, $_GET, $body, $headers);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function body(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = strtolower(str_replace('_', '-', $key));
        return $this->headers[$normalized] ?? $default;
    }

    public function getIp(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '127.0.0.1';
    }
}
