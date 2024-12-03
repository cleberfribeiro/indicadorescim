<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Tipo de dados que está sendo salvo ('geral' ou 'perdas')
    $tipo = $data['tipo'];
    $timestamp = date('Y-m-d_H-i-s');
    $filename = __DIR__ . "/dados/{$tipo}_dados_{$timestamp}.txt";

    $content = "Dados atualizados em: " . date('d/m/Y H:i:s') . "\n\n";

    if ($tipo === 'geral') {
        $content .= "Gestão de Estoque:\n";
        foreach ($data['data'] as $item) {
            $content .= "Localização: {$item['localizacao']}, ";
            $content .= "Posições Totais: {$item['posicoesTotais']}, ";
            $content .= "Vazias Real: {$item['vaziasReal']}\n";
        }
    } else if ($tipo === 'perdas') {
        $content .= "Detalhes de Perdas / Reservas:\n";
        foreach ($data['data'] as $item) {
            $content .= "Localização: {$item['localizacao']}, ";
            $content .= "Hidrante/Viga: {$item['hidrante']}, ";
            $content .= "Corredor: {$item['corredor']}, ";
            $content .= "Reservado/PED: {$item['reservadoPed']}, ";
            $content .= "Reserv/ALM: {$item['reservAlm']}, ";
            $content .= "Amassado/Inexistente: {$item['amassado']}\n";
        }
    }

    // Salvar o conteúdo no arquivo
    if (file_put_contents($filename, $content) !== false) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar os dados.']);
    }
}
?>
