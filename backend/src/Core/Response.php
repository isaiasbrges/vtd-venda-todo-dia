<?php
declare(strict_types=1);

namespace VTD\Core;

class Response
{
    public static bool $stopOnExit = true;

    public static function json(mixed $data, int $status = 200, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            foreach ($headers as $key => $val) {
                header("{$key}: {$val}");
            }
        }

        echo json_encode([
            'success' => $status >= 200 && $status < 400,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (self::$stopOnExit) {
            exit;
        }
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        echo json_encode([
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
                'details' => $details
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (self::$stopOnExit) {
            exit;
        }
    }

    public static function streamCsv(string $filename, array $headers, array $rows): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        $output = fopen('php://output', 'w');
        // UTF-8 BOM para Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }
        fclose($output);
        
        if (self::$stopOnExit) {
            exit;
        }
    }
}
