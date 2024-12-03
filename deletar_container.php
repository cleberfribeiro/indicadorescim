<?php
session_start();
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';

// Verifica se está logado e é admin
$usuarioLogado = verificarLogin();
if (!isAdmin()) {
    setMensagem('error', 'Acesso negado - Apenas administradores podem excluir registros');
    header('Location: recebimento.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $container = lerContainer($id);
    
    if ($container && deletarContainer($id)) {
        registrar_log("Container {$container['numero_container']} (Ordem: {$container['ordem_descarga']}) excluído por {$_SESSION['usuario']}");
        setMensagem('success', 'Container excluído com sucesso!');
    } else {
        setMensagem('error', 'Erro ao excluir o container. Tente novamente.');
    }
} else {
    setMensagem('error', 'Container não encontrado.');
}

header('Location: recebimento.php');
exit;
?>