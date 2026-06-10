<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SpreadsheetImporter.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use Remanejamento\Database;
use Remanejamento\SpreadsheetImporter;

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['tipo' => 'erro', 'msg' => 'Token CSRF inválido. Tente novamente.'];
    header('Location: index.php');
    exit;
}

$unidadeId = filter_input(INPUT_POST, 'unidade_id', FILTER_VALIDATE_INT);
if (!$unidadeId || $unidadeId <= 0) {
    $_SESSION['flash'] = ['tipo' => 'erro', 'msg' => 'Selecione uma unidade válida para a importação.'];
    header('Location: index.php');
    exit;
}

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] === UPLOAD_ERR_NO_FILE) {
    $_SESSION['flash'] = ['tipo' => 'erro', 'msg' => 'Nenhum arquivo selecionado.'];
    header('Location: index.php');
    exit;
}

try {
    $pdo       = Database::getInstance();
    $importer  = new SpreadsheetImporter($pdo);
    $usuario   = $_SESSION['usuario'] ?? 'anonimo';

    $resultado = $importer->processarUpload($_FILES['arquivo'], $unidadeId, $usuario);

    if ($resultado['sucesso']) {
        $msg = "✔ Importação concluída: {$resultado['importados']} registro(s) importado(s)";
        if ($resultado['ignorados'] > 0) {
            $msg .= ", {$resultado['ignorados']} ignorado(s)";
        }
        if (!empty($resultado['erros'])) {
            $msg .= '. Erros: ' . implode(' | ', array_slice($resultado['erros'], 0, 3));
        }
        $_SESSION['flash'] = ['tipo' => 'sucesso', 'msg' => $msg];
    } else {
        $_SESSION['flash'] = [
            'tipo' => 'erro',
            'msg'  => 'Falha na importação: ' . $resultado['mensagem'],
        ];
    }
} catch (\Throwable $e) {
    $_SESSION['flash'] = [
        'tipo' => 'erro',
        'msg'  => 'Erro interno: ' . htmlspecialchars($e->getMessage()),
    ];
}

header('Location: index.php');
exit;
