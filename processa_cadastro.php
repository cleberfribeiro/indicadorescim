<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = $_POST['nome_completo'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $login = $_POST['login'];
    $senha = $_POST['senha'];
    $confirma_senha = $_POST['confirma_senha'];

    if ($senha !== $confirma_senha) {
        echo "<script>alert('As senhas não coincidem. Por favor, tente novamente.'); window.history.back();</script>";
        exit();
    }

    // Verifica se o login já existe
    $usuarios = file('dados/usuarios.txt');
    foreach ($usuarios as $linha) {
        $dados_usuario = explode(',', trim($linha));
        if ($dados_usuario[3] === $login) {
            echo "<script>alert('Esse login já está em uso. Por favor, escolha outro.'); window.history.back();</script>";
            exit();
        }
    }

    // Adiciona o novo usuário
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    $novo_usuario = "$nome_completo, $email, $telefone, $login, $senha_hash\n";
    file_put_contents('dados/usuarios.txt', $novo_usuario, FILE_APPEND);

    // Função para registrar log corretamente
    function registrar_log($mensagem) {
        $data_hora = date('d/m/Y H:i:s'); // Formata data e hora no formato desejado
        $log_mensagem = "$data_hora | $mensagem\n"; // Ajusta o formato do log
        file_put_contents('dados/logs.txt', $log_mensagem, FILE_APPEND);
    }

    // Registra no log a adição do usuário
    $usuario_logado = $_SESSION['usuario'];
    registrar_log("master | Adicionou novo usuário | Usuário '$nome_completo' foi adicionado por '$usuario_logado'.");

    echo "<script>alert('Usuário cadastrado com sucesso!'); window.location.href='configuracoes.php';</script>";
    exit();
}
?>
