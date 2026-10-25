<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\AuthMiddleware;

class CalendarController
{
    public function list(Request $request): void
    {
        (new AuthMiddleware())->handle($request);

        $pdo = Connection::getInstance();
        $stmt = $pdo->query("SELECT * FROM calendar_months ORDER BY month_number ASC");
        $rows = $stmt->fetchAll();

        $months = array_map(function($r) {
            return [
                'id'    => (int)$r['id'],
                'n'     => $r['name'],
                's'     => $r['short_name'],
                'lead'  => $r['lead'],
                'buy'   => $r['buy_tip'],
                'tip'   => $r['practical_tip'],
                'dates' => json_decode($r['dates'], true),
                'items' => json_decode($r['suggested_items'], true)
            ];
        }, $rows);

        Response::json($months);
    }
}
