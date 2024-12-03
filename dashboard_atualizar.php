<?php
$directory = 'dados';
$file = $directory . '/estoque_dados.txt';

// Verifica se o arquivo existe
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);

    // Inicializa variáveis para os totais
    $totalPosicoesOcupadas = 0;
    $totalPerdas = 0;

    // Processa os dados para calcular totais
    foreach ($data as $key => $value) {
        if (strpos($key, 'geral') !== false) {
            if (strpos($key, 'posicoesOcupadas') !== false) {
                $totalPosicoesOcupadas += (int)$value;
            }
            if (strpos($key, 'perdasReserva') !== false) {
                $totalPerdas += (int)$value;
            }
        }
    }

    // Retorna os totais em formato JSON
    $dashboardData = [
        'totalPosicoesOcupadas' => $totalPosicoesOcupadas,
        'totalPerdas' => $totalPerdas
    ];

    echo json_encode($dashboardData);
} else {
    echo json_encode([]);
}
?>
