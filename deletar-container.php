<?php
require_once 'funcoes_auth.php';
require_once 'conexao.php';
verificarLogin();

if (!isset($_GET['id'])) {
    header('Location: lista_containers.php');
    exit();
}

$id = $_GET['id'];

try {
    // Primeiro verifica se o container existe
    $sql = "SELECT id FROM containers WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Container não encontrado');
    }
    
    // Se existe, então deleta
    $sql = "DELETE FROM containers WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    $_SESSION['sucesso'] = 'Container excluído com sucesso!';
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao excluir container: ' . $e->getMessage();
}

header('Location: lista_containers.php');
exit();
?>