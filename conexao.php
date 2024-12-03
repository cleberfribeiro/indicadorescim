<?php
// Define o diretório de dados
define('DIR_DADOS', __DIR__ . '/dados');

// Garante que a pasta dados existe
if (!is_dir(DIR_DADOS)) {
    mkdir(DIR_DADOS, 0777, true);
}

// Garante que a pasta fotos existe
if (!is_dir(DIR_DADOS . '/fotos')) {
    mkdir(DIR_DADOS . '/fotos', 0777, true);
}

// Função para registrar logs
function registrar_log($mensagem) {
    $data = date('Y-m-d H:i:s');
    $log = "$data - $mensagem" . PHP_EOL;
    file_put_contents(DIR_DADOS . '/log.txt', $log, FILE_APPEND);
}

// Função para manipulação das mensagens da sessão
function setMensagem($tipo, $texto) {
    $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
}

function getMensagem() {
    if (isset($_SESSION['mensagem'])) {
        $mensagem = $_SESSION['mensagem'];
        unset($_SESSION['mensagem']);
        return $mensagem;
    }
    return null;
}
?>
