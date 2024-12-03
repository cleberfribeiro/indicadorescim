<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$usuario_logado = $_SESSION['usuario'];

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dados do formulário
    $nome_completo = $_POST['nome_completo'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];

    // Carrega os dados dos usuários
    $usuarios = file('dados/usuarios.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $usuarios_atualizados = [];

    // Atualiza as informações do usuário logado
    foreach ($usuarios as $usuario) {
        $dados_usuario = explode(',', $usuario);
        if (count($dados_usuario) >= 5) {
            list($nome, $email_lido, $telefone_lido, $login, $senha_hash) = $dados_usuario;

            if ($login === $usuario_logado) {
                // Atualizar os dados
                $dados_usuario[0] = $nome_completo;
                $dados_usuario[1] = $email;
                $dados_usuario[2] = $telefone;
            }

            // Adiciona os dados atualizados à lista
            $usuarios_atualizados[] = implode(',', $dados_usuario);
        }
    }

    // Grava os dados atualizados no arquivo
    file_put_contents('dados/usuarios.txt', implode(PHP_EOL, $usuarios_atualizados) . PHP_EOL);

    // Redireciona de volta para a página de configurações com uma mensagem de sucesso
    $_SESSION['mensagem_sucesso'] = 'Perfil atualizado com sucesso!';
    header("Location: configuracoes.php");
    exit();
}
