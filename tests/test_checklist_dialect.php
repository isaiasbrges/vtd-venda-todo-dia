<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
class ChecklistStatement extends PDOStatement {
    public array $bound = [];
    public function __construct() {}
    public function execute(?array $params = null): bool { $this->bound = $params ?? []; return true; }
}
class ChecklistMysqlConnection extends PDO {
    public array $queries = [];
    public ChecklistStatement $statement;
    public function __construct() { $this->statement = new ChecklistStatement(); }
    public function getAttribute(int $attribute): mixed { return 'mysql'; }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_contains($query, 'ON CONFLICT')) throw new RuntimeException('SQLite SQL reached native MySQL prepare');
        $this->queries[] = $query;
        return $this->statement;
    }
}
$instance = new ReflectionProperty(\VTD\Database\Connection::class, 'instance');
$mysql = new ChecklistMysqlConnection();
$instance->setValue(null, $mysql);
$controller = new \VTD\Controllers\UserContentController();
foreach ([true, false] as $value) {
    $controller->saveChecklist('user-a', 'home-products', $value);
    check($mysql->statement->bound[':chk'] === (int)$value, 'Wrong checked value');
    check($mysql->statement->bound[':chk2'] === (int)$value, 'Wrong updated value');
}
check(count($mysql->queries) === 2, 'Must prepare once per write');
check(str_contains($mysql->queries[0], 'ON DUPLICATE KEY UPDATE'), 'MySQL upsert missing');
echo "PASS: MySQL dialect selected before prepare (PDO test double).\n";

$sqlite = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$sqlite->exec('CREATE TABLE user_checklists (user_id TEXT, check_key TEXT, checked INTEGER, updated_at TEXT, PRIMARY KEY(user_id,check_key))');
$instance->setValue(null, $sqlite);
$controller->saveChecklist('user-a', 'home-products', true);
$controller->saveChecklist('user-a', 'home-products', true);
$controller->saveChecklist('user-b', 'home-products', true);
check($controller->getUserChecklists('user-a')['home-products'] === true, 'Check did not persist');
check((int)$sqlite->query('SELECT COUNT(*) FROM user_checklists')->fetchColumn() === 2, 'Upsert duplicated rows');
$controller->saveChecklist('user-a', 'home-products', false);
check(!isset($controller->getUserChecklists('user-a')['home-products']), 'Uncheck did not persist');
check($controller->getUserChecklists('user-b')['home-products'] === true, 'Other user changed');
$instance->setValue(null, null);
echo "PASS: SQLite insert, repeat update, uncheck and user isolation.\n";
