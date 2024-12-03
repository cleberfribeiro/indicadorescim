<?php
// Verifica se a pasta 'dados' existe, caso contrário, cria
$directory = 'dados';
if (!is_dir($directory)) {
    mkdir($directory, 0755, true); // Cria a pasta
}

// Inicializa um array para armazenar os dados
$data = [];
foreach ($_POST as $key => $value) {
    $data[$key] = $value;
}

// Salva os dados no arquivo estoque_dados.txt na pasta 'dados'
file_put_contents($directory . '/estoque_dados.txt', json_encode($data));
echo "Dados salvos com sucesso!";
?>
