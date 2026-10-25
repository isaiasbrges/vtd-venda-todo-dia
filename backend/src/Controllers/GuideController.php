<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\AuthMiddleware;

class GuideController
{
    public function list(Request $request): void
    {
        (new AuthMiddleware())->handle($request);

        $pdo = Connection::getInstance();
        $stmt = $pdo->query("SELECT * FROM guide_chapters ORDER BY chapter_number ASC");
        $rows = $stmt->fetchAll();

        $chapters = array_map(function($r) {
            return [
                'id'    => (int)$r['id'],
                'name'  => $r['name'],
                'title' => $r['title'],
                'body'  => $r['body'],
                'task'  => $r['task']
            ];
        }, $rows);

        Response::json($chapters);
    }
}
