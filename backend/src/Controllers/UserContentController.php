<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\AuthMiddleware;
use VTD\Security\SessionManager;

class UserContentController
{
    // --- Favoritos ---

    public function getUserFavorites(string $userId): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT supplier_id FROM user_favorites WHERE user_id = :uid ORDER BY created_at DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function toggleFavorite(string $userId, string $supplierId, bool $isFavorite): void
    {
        $pdo = Connection::getInstance();
        if ($isFavorite) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO user_favorites (user_id, supplier_id) VALUES (:uid, :sid)");
            if (\VTD\Config\Config::get('db.driver') === 'mysql') {
                $stmt = $pdo->prepare("INSERT IGNORE INTO user_favorites (user_id, supplier_id) VALUES (:uid, :sid)");
            }
            $stmt->execute([':uid' => $userId, ':sid' => $supplierId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = :uid AND supplier_id = :sid");
            $stmt->execute([':uid' => $userId, ':sid' => $supplierId]);
        }
    }

    public function getFavorites(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        $favs = $this->getUserFavorites($userId);
        Response::json($favs);
    }

    public function setFavorite(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        $supplierId = (string)$request->body('supplier_id', '');
        $status = $request->body('status', true);

        if (empty($supplierId)) {
            Response::error('VALIDATION_ERROR', 'ID do fornecedor é obrigatório.', 422);
        }

        $this->toggleFavorite($userId, $supplierId, (bool)$status);
        Response::json(['favorites' => $this->getUserFavorites($userId)]);
    }

    // --- Checklists ---

    public function getUserChecklists(string $userId): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT check_key, checked FROM user_checklists WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            if (!empty($r['checked'])) {
                $map[$r['check_key']] = true;
            }
        }
        return $map;
    }

    public function saveChecklist(string $userId, string $checkKey, bool $checked): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            INSERT INTO user_checklists (user_id, check_key, checked, updated_at)
            VALUES (:uid, :key, :chk, CURRENT_TIMESTAMP)
            ON CONFLICT(user_id, check_key) DO UPDATE SET
                checked = :chk2,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO user_checklists (user_id, check_key, checked, updated_at)
                VALUES (:uid, :key, :chk, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    checked = :chk2,
                    updated_at = CURRENT_TIMESTAMP
            ");
        }
        $val = $checked ? 1 : 0;
        $stmt->execute([':uid' => $userId, ':key' => $checkKey, ':chk' => $val, ':chk2' => $val]);
    }

    public function getChecklists(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        Response::json($this->getUserChecklists($userId));
    }

    public function setChecklist(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        $key = trim((string)$request->body('key', ''));
        $checked = (bool)$request->body('checked', false);

        if (empty($key)) {
            Response::error('VALIDATION_ERROR', 'Chave do checklist é obrigatória.', 422);
        }

        $this->saveChecklist($userId, $key, $checked);
        Response::json(['checklists' => $this->getUserChecklists($userId)]);
    }

    // --- Progresso de Leitura e Anotações ---

    public function getUserReadingProgress(string $userId): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT chapter_id FROM user_chapter_progress WHERE user_id = :uid AND completed = 1");
        $stmt->execute([':uid' => $userId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function saveChapterProgress(string $userId, int $chapterId, bool $completed): void
    {
        $pdo = Connection::getInstance();
        $val = $completed ? 1 : 0;
        $stmt = $pdo->prepare("
            INSERT INTO user_chapter_progress (user_id, chapter_id, completed, updated_at)
            VALUES (:uid, :cid, :comp, CURRENT_TIMESTAMP)
            ON CONFLICT(user_id, chapter_id) DO UPDATE SET
                completed = :comp2,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO user_chapter_progress (user_id, chapter_id, completed, updated_at)
                VALUES (:uid, :cid, :comp, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    completed = :comp2,
                    updated_at = CURRENT_TIMESTAMP
            ");
        }
        $stmt->execute([':uid' => $userId, ':cid' => $chapterId, ':comp' => $val, ':comp2' => $val]);
    }

    public function getUserNotes(string $userId): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT chapter_id, note FROM user_chapter_progress WHERE user_id = :uid AND note IS NOT NULL");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['chapter_id']] = (string)$r['note'];
        }
        return $map;
    }

    public function saveChapterNote(string $userId, int $chapterId, string $note): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            INSERT INTO user_chapter_progress (user_id, chapter_id, note, updated_at)
            VALUES (:uid, :cid, :note, CURRENT_TIMESTAMP)
            ON CONFLICT(user_id, chapter_id) DO UPDATE SET
                note = :note2,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO user_chapter_progress (user_id, chapter_id, note, updated_at)
                VALUES (:uid, :cid, :note, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    note = :note2,
                    updated_at = CURRENT_TIMESTAMP
            ");
        }
        $stmt->execute([':uid' => $userId, ':cid' => $chapterId, ':note' => $note, ':note2' => $note]);
    }

    public function getReadingProgress(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        Response::json($this->getUserReadingProgress($userId));
    }

    public function setReadingProgress(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        $chapterId = (int)$request->body('chapter_id', 0);
        $completed = (bool)$request->body('completed', false);

        if ($chapterId <= 0) {
            Response::error('VALIDATION_ERROR', 'ID do capítulo inválido.', 422);
        }

        $this->saveChapterProgress($userId, $chapterId, $completed);
        Response::json(['progress' => $this->getUserReadingProgress($userId)]);
    }

    public function getNotes(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        Response::json($this->getUserNotes($userId));
    }

    public function setNote(Request $request): void
    {
        (new AuthMiddleware())->handle($request);
        $userId = SessionManager::get('user_id');
        $chapterId = (int)$request->body('chapter_id', 0);
        $note = (string)$request->body('note', '');

        if ($chapterId <= 0) {
            Response::error('VALIDATION_ERROR', 'ID do capítulo inválido.', 422);
        }

        $this->saveChapterNote($userId, $chapterId, $note);
        Response::json(['notes' => $this->getUserNotes($userId)]);
    }
}
