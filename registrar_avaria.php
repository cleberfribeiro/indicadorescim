<?php
session_start();
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';

// Verificar se está logado
$usuarioLogado = verificarLogin();

// Garantir que a resposta seja JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Validar dados obrigatórios
    if (empty($_POST['container_id']) || empty($_POST['codigo_produto']) || empty($_POST['tipo_avaria']) || empty($_POST['descricao'])) {
        throw new Exception('Todos os campos são obrigatórios');
    }

    if (empty($_FILES['fotos']['name'][0])) {
        throw new Exception('É necessário enviar pelo menos uma foto');
    }

    $containerId = $_POST['container_id'];
    $container = lerContainer($containerId);
    
    if (!$container) {
        throw new Exception('Container não encontrado');
    }

    // Verificar tipos de arquivo permitidos
    $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png'];
    foreach ($_FILES['fotos']['type'] as $tipo) {
        if (!in_array($tipo, $tiposPermitidos)) {
            throw new Exception('Tipo de arquivo não permitido. Use apenas JPG ou PNG.');
        }
    }

    $dados = [
        'codigo_produto' => strtoupper($_POST['codigo_produto']),
        'tipo_avaria' => strtoupper($_POST['tipo_avaria']),
        'descricao' => strtoupper($_POST['descricao']),
        'fotos' => $_FILES['fotos']
    ];

    if (salvarAvaria($containerId, $dados)) {
        registrar_log("Avaria registrada para o container {$container['numero_container']} - Produto: {$dados['codigo_produto']} por {$_SESSION['usuario']}");
        echo json_encode(['success' => true, 'message' => 'Avaria registrada com sucesso']);
    } else {
        throw new Exception('Erro ao salvar avaria');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
