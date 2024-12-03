<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Sao_Paulo');

require_once 'separacao.php';

class RegistroImportacao {
    private $arquivoRegistro = 'registro_importacoes.txt';

    public function getRegistros() {
        if (!file_exists($this->arquivoRegistro)) {
            return [];
        }
        $conteudo = file_get_contents($this->arquivoRegistro);
        return json_decode($conteudo, true) ?? [];
    }

    public function getEstatisticasPeriodo($periodo = 'semanal') {
        $registros = $this->getRegistros();
        $estatisticas = [];

        foreach ($registros as $registro) {
            $key = match($periodo) {
                'semanal' => $registro['ano'] . '-S' . $registro['semana'],
                'mensal' => $registro['ano'] . '-' . $registro['mes'],
                'anual' => $registro['ano'],
                default => $registro['data']
            };

            if (!isset($estatisticas[$key])) {
                $estatisticas[$key] = [
                    'total_pedidos' => 0,
                    'pendentes' => 0,
                    'concluidos' => 0,
                    'em_andamento' => 0
                ];
            }

            $estatisticas[$key]['total_pedidos'] += $registro['indicadores']['total'];
            $estatisticas[$key]['pendentes'] += $registro['indicadores']['pendentes'];
            $estatisticas[$key]['concluidos'] += $registro['indicadores']['concluidos'];
            $estatisticas[$key]['em_andamento'] += $registro['indicadores']['em_andamento'];
        }

        ksort($estatisticas);
        return $estatisticas;
    }
}

$periodo = $_GET['periodo'] ?? 'semanal';
$painel = new PainelSeparacao();
$estatisticas = $painel->getEstatisticas($periodo);

echo json_encode($estatisticas);