<?php
function registrarLog($mensagem) {
    $arquivo = fopen('dados/logs.txt', 'a');
    $dataHora = date('Y-m-d H:i:s'); // Data e hora atual
    fwrite($arquivo, "[$dataHora] $mensagem\n");
    fclose($arquivo);
}
?>
