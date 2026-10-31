<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// 1. Bloqueio de segurança para backend, banco sqlite e arquivos ocultos
if (
    str_starts_with($uri, '/backend') ||
    str_starts_with($uri, '/.') ||
    str_contains($uri, '.sqlite') ||
    str_starts_with($uri, '/tests')
) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

// 2. Roteamento de endpoints de API (/api/* -> api.php)
if (str_starts_with($uri, '/api')) {
    $_GET['endpoint'] = substr($uri, 4) ?: '/';
    require __DIR__ . '/api.php';
    exit;
}

// 3. Raiz: serve index.html
if ($uri === '/' || $uri === '') {
    $filePath = __DIR__ . '/index.html';
    if (file_exists($filePath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
        exit;
    }
}

// 4. Arquivos estáticos (app.js, styles.css, data.js, imagens, etc.)
$filePath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if (is_file($filePath)) {
    // O servidor embutido do PHP não trata intervalos de MP4. Servir os bytes
    // solicitados permite avançar o vídeo na prévia local, como na hospedagem.
    if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'mp4') {
        $size = filesize($filePath);
        $start = 0;
        $end = $size - 1;
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range !== null) {
            if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $match) || ($match[1] === '' && $match[2] === '')) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            if ($match[1] === '') {
                $start = max(0, $size - (int) $match[2]);
            } else {
                $start = (int) $match[1];
                $end = $match[2] === '' ? $end : min($end, (int) $match[2]);
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
        }
        $stream = fopen($filePath, 'rb');
        if ($stream === false) {
            http_response_code(500);
            exit;
        }
        header('Content-Type: video/mp4');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        if ($range !== null) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            fseek($stream, $start);
            $remaining = $end - $start + 1;
            while ($remaining > 0 && !feof($stream) && !connection_aborted()) {
                $chunk = fread($stream, min(262144, $remaining));
                if ($chunk === false || $chunk === '') break;
                echo $chunk;
                $remaining -= strlen($chunk);
            }
        }
        fclose($stream);
        exit;
    }
    return false; // Permite que o servidor embutido do PHP sirva diretamente
}

// 5. Fallback 404
http_response_code(404);
echo "404 Not Found";
