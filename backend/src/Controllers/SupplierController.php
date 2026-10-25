<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\AuthMiddleware;

class SupplierController
{
    public function querySuppliers(array $filters = []): array
    {
        $pdo = Connection::getInstance();

        $search = trim((string)($filters['search'] ?? ''));
        $segment = trim((string)($filters['segment'] ?? ''));
        $waOnly = !empty($filters['wa']);
        $catOnly = !empty($filters['cat']);
        $favsUserId = $filters['favs_user_id'] ?? null;
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;

        $conditions = ["is_active = 1"];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(name LIKE :s1 OR brands LIKE :s2 OR products LIKE :s3 OR category LIKE :s4)";
            $searchTerm = "%{$search}%";
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
            $params[':s4'] = $searchTerm;
        }

        if (!empty($segment)) {
            $conditions[] = "segment = :segment";
            $params[':segment'] = $segment;
        }

        if ($waOnly) {
            $conditions[] = "(whatsapp IS NOT NULL AND whatsapp != '[]' AND whatsapp != '')";
        }

        if ($catOnly) {
            $conditions[] = "(catalogs IS NOT NULL AND catalogs != '[]' AND catalogs != '')";
        }

        if (!empty($favsUserId)) {
            $conditions[] = "id IN (SELECT supplier_id FROM user_favorites WHERE user_id = :fav_uid)";
            $params[':fav_uid'] = $favsUserId;
        }

        $whereClause = implode(" AND ", $conditions);

        // Count total
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        // Query page
        $sql = "SELECT * FROM suppliers WHERE {$whereClause} ORDER BY score DESC, name ASC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Deserializa campos JSON para manter formato de data.js esperado pelo front
        $suppliers = array_map(function($r) {
            return [
                'id'        => (string)$r['id'],
                'nome'      => $r['name'],
                'marcas'    => $r['brands'] ?? '',
                'produtos'  => $r['products'] ?? '',
                'categoria' => $r['category'] ?? '',
                'seg'       => $r['segment'] ?? '',
                'site'      => $r['site'] ?? '',
                'ig'        => $r['instagram'] ?? '',
                'igh'       => $r['instagram_handle'] ?? '',
                'wa'        => !empty($r['whatsapp']) ? json_decode($r['whatsapp'], true) : [],
                'tel'       => !empty($r['phones']) ? json_decode($r['phones'], true) : [],
                'email'     => $r['email'] ?? '',
                'fb'        => $r['facebook'] ?? '',
                'yt'        => $r['youtube'] ?? '',
                'li'        => $r['linkedin'] ?? '',
                'tt'        => $r['tiktok'] ?? '',
                'cat'       => !empty($r['catalogs']) ? json_decode($r['catalogs'], true) : [],
                'contato'   => $r['contact_url'] ?? '',
                'score'     => (int)$r['score'],
                'nota'      => $r['notes'] ?? '',
                // Aliases em inglês também caso consumido
                'name'      => $r['name'],
                'brands'    => $r['brands'] ?? '',
                'products'  => $r['products'] ?? '',
                'category'  => $r['category'] ?? '',
                'segment'   => $r['segment'] ?? ''
            ];
        }, $rows);

        $totalPages = max(1, (int)ceil($total / $limit));

        return [
            'suppliers'   => $suppliers,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $totalPages
        ];
    }

    public function list(Request $request): void
    {
        (new AuthMiddleware())->handle($request);

        $filters = [
            'search'       => $request->query('search', ''),
            'segment'      => $request->query('segment', ''),
            'wa'           => $request->query('wa') === '1' || $request->query('wa') === 'true',
            'cat'          => $request->query('cat') === '1' || $request->query('cat') === 'true',
            'favs_user_id' => $request->query('favs') ? \VTD\Security\SessionManager::get('user_id') : null,
            'page'         => (int)$request->query('page', 1),
            'limit'        => (int)$request->query('limit', 12)
        ];

        $data = $this->querySuppliers($filters);
        Response::json($data);
    }

    public function detail(Request $request, array $params): void
    {
        (new AuthMiddleware())->handle($request);

        $id = $params['id'] ?? '';
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $id]);
        $r = $stmt->fetch();

        if (!$r) {
            Response::error('NOT_FOUND', 'Fornecedor não encontrado.', 404);
        }

        $supplier = [
            'id'        => (string)$r['id'],
            'nome'      => $r['name'],
            'marcas'    => $r['brands'] ?? '',
            'produtos'  => $r['products'] ?? '',
            'categoria' => $r['category'] ?? '',
            'seg'       => $r['segment'] ?? '',
            'site'      => $r['site'] ?? '',
            'ig'        => $r['instagram'] ?? '',
            'igh'       => $r['instagram_handle'] ?? '',
            'wa'        => !empty($r['whatsapp']) ? json_decode($r['whatsapp'], true) : [],
            'tel'       => !empty($r['phones']) ? json_decode($r['phones'], true) : [],
            'email'     => $r['email'] ?? '',
            'fb'        => $r['facebook'] ?? '',
            'yt'        => $r['youtube'] ?? '',
            'li'        => $r['linkedin'] ?? '',
            'tt'        => $r['tiktok'] ?? '',
            'cat'       => !empty($r['catalogs']) ? json_decode($r['catalogs'], true) : [],
            'contato'   => $r['contact_url'] ?? '',
            'score'     => (int)$r['score'],
            'nota'      => $r['notes'] ?? ''
        ];

        Response::json($supplier);
    }
}
