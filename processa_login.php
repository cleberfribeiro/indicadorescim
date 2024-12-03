<?php
session_start();
include('registrar_log.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST['login'];
    $senha = $_POST['senha'];

    // Carrega os dados dos usuários
    if (file_exists('dados/usuarios.txt')) {
        $usuarios = file('dados/usuarios.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($usuarios as $linha) {
            $dados_usuario = explode(',', $linha);
            if (count($dados_usuario) >= 5) {
                list($nome, $email, $telefone, $login_stored, $senha_hash) = $dados_usuario;
                if ($login === $login_stored && password_verify($senha, $senha_hash)) {
                    $_SESSION['usuario'] = $login;
                    registrar_log("Usuário '$login' fez login.");
                    header("Location: principal.php");
                    exit();
                }
            }
        }
    }

    // Se o login falhar
    registrar_log("Tentativa de login falhou: usuário $login não encontrado.");
    header("Location: index.php?erro=1");
}
?>
