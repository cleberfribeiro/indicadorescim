<?php
session_start();
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';

// Verificar se está logado
verificarLogin();

header('Content-Type: application/json');

if (!isset($_GET['codigo'])) {
    echo json_encode(['success' => false, 'message' => 'Código não fornecido']);
    exit;
}

$codigo = $_GET['codigo'];
$produto = buscarProdutoExcel($codigo);

if ($produto) {
    echo json_encode(['success' => true, 'data' => $produto]);
} else {
    echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
}
?>
