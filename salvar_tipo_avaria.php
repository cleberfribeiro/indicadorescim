<?php
session_start();
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';

// Verificar se está logado
verificarLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['valor'])) {
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
    exit;
}

try {
    $tipo = $_POST['valor'];
    salvarTipoAvaria($tipo);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
