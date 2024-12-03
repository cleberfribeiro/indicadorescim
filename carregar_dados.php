<?php
// Definir o cabeçalho de resposta como JSON para evitar problemas de cache
header('Content-Type: application/json');

// Caminho do arquivo onde os dados estão armazenados
$file = 'dados/estoque_dados.txt';

if (file_exists($file)) {
    // Ler os dados do arquivo
    $data = file_get_contents($file);
    $data = json_decode($data, true); // Decodificar o JSON

    // Verificar se a decodificação foi bem-sucedida
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Erro ao decodificar JSON.']);
    } else {
        // Retornar os dados em formato JSON
        echo json_encode($data);
    }
} else {
    // Caso o arquivo não exista, retornar um erro
    echo json_encode(['error' => 'Arquivo de dados não encontrado.']);
}
?>
