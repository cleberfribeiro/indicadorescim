<?php
function lerUsuarios() {
    if (!file_exists("dados/usuarios.txt")) {
        return [];
    }
    
    $usuarios = [];
    $linhas = file("dados/usuarios.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($linhas as $linha) {
        $dados = explode(",", $linha);
        if (count($dados) >= 5) {
            $usuarios[] = [
                'nome' => $dados[0],
                'email' => $dados[1],
                'telefone' => $dados[2],
                'usuario' => $dados[3],
                'senha' => $dados[4]
            ];
        }
    }
    
    return $usuarios;
}

function getUsuarioLogado() {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $usuarios = lerUsuarios();
    foreach ($usuarios as $usuario) {
        if ($usuario['usuario'] === $_SESSION['usuario']) {
            return $usuario;
        }
    }
    
    return false;
}

function verificarLogin() {
    if (!isset($_SESSION['usuario'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: index.php');
        exit;
    }
    return getUsuarioLogado();
}

function isAdmin() {
    return isset($_SESSION['usuario']) && $_SESSION['usuario'] === 'master';
}

// Função para registrar logs
function registrar_log($mensagem) {
    $data = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'];
    $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Sistema';
    $log = "$data | $ip | $usuario | $mensagem\n";
    file_put_contents('dados/log.txt', $log, FILE_APPEND);
}

// Função para exibir mensagem de feedback
function setMensagem($tipo, $texto) {
    $_SESSION['mensagem'] = [
        'tipo' => $tipo,
        'texto' => $texto
    ];
}

// Função para obter e limpar mensagem
function getMensagem() {
    if (isset($_SESSION['mensagem'])) {
        $mensagem = $_SESSION['mensagem'];
        unset($_SESSION['mensagem']);
        return $mensagem;
    }
    return null;
}
?>