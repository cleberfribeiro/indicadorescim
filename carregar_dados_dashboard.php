<?php
// Supondo que você tenha salvo os dados em um arquivo `estoque_dados.txt` na pasta `dados`
$dados = file_get_contents('dados/estoque_dados.txt');
$linhas = explode("\n", $dados);

$data = [];

// Preenche as variáveis de acordo com as linhas e colunas do arquivo de dados
$data['enderecosTotais'] = 0;
$data['enderecosOcupados'] = 0;
$data['enderecosVazios'] = 0;
$data['perdasReservas'] = 0;

foreach ($linhas as $linha) {
    if (!empty($linha)) {
        $colunas = explode(',', $linha);
        // Supondo que as colunas estejam no formato: Localização, Posições Totais, Posições Ocupadas, Vazias Real, Perdas / Reserva
        $data['enderecosTotais'] += (int)$colunas[1];
        $data['enderecosOcupados'] += (int)$colunas[2];
        $data['enderecosVazios'] += (int)$colunas[3];
        $data['perdasReservas'] += (int)$colunas[4];
        
        // Preenche arrays para gráficos
        $data['posicoesTotais'][] = (int)$colunas[1];
        $data['posicoesOcupadas'][] = (int)$colunas[2];
        $data['vaziasSemPerdas'][] = (int)$colunas[3] - (int)$colunas[4]; // Vazias sem perdas
        $data['perdasReservas'][] = (int)$colunas[4];
        $data['percentualOcupado'][] = round(((int)$colunas[2] / (int)$colunas[1]) * 100);
        $data['percentualPerdaReservada'][] = round(((int)$colunas[4] / (int)$colunas[1]) * 100);
    }
}

// Retorna os dados como JSON
header('Content-Type: application/json');
echo json_encode($data);
