<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Sao_Paulo');
session_start();
require 'vendor/autoload.php';

class RegistroImportacao {
    private $arquivoRegistro = 'registro_importacoes.txt';

    public function __construct() {
        if (!file_exists($this->arquivoRegistro)) {
            file_put_contents($this->arquivoRegistro, '[]');
        }
    }

    public function registrarImportacao($totalRegistros, $indicadores) {
        $registros = $this->getRegistros();
        $registro = [
            'data' => date('Y-m-d H:i:s'),
            'semana' => date('W'),
            'mes' => date('m'),
            'ano' => date('Y'),
            'total_registros' => $totalRegistros,
            'indicadores' => $indicadores
        ];
        array_push($registros, $registro);
        file_put_contents($this->arquivoRegistro, json_encode($registros, JSON_PRETTY_PRINT));
    }

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

class PainelSeparacao {
    protected $arquivoDados = 'dados_separacao.txt';
    private $registroImportacao;
    
    public function __construct() {
        if (!file_exists($this->arquivoDados)) {
            file_put_contents($this->arquivoDados, '[]');
        }
        $this->registroImportacao = new RegistroImportacao();
    }

    public function getDados() {
        $conteudo = file_get_contents($this->arquivoDados);
        return json_decode($conteudo, true) ?? [];
    }

    public function importarDados($arquivo) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($arquivo['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $dados = [];

            // Obter cabeçalhos
            $headers = [];
            foreach ($worksheet->getRowIterator(1, 1) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $valor = trim($cell->getValue());
                    $headers[] = $valor;
                }
                break;
            }

            // Processar dados
            $rowCount = 2;
            while ($worksheet->getCell('A' . $rowCount)->getValue() !== null) {
                $linha = [];
                $colCount = 0;
                foreach ($headers as $header) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount + 1);
                    $valor = $worksheet->getCell($colLetter . $rowCount)->getValue();
                    $linha[$header] = trim($valor ?? '');
                    $colCount++;
                }
                if (!empty(array_filter($linha))) {
                    $dados[] = $linha;
                }
                $rowCount++;
            }

            $codificado = json_encode($dados, JSON_UNESCAPED_UNICODE);
            file_put_contents($this->arquivoDados, $codificado);
            
            $indicadores = $this->obterIndicadores();
            $this->registroImportacao->registrarImportacao(count($dados), $indicadores);
            
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao processar arquivo Excel: " . $e->getMessage());
            return false;
        }
    }

    public function obterIndicadores() {
        $dados = $this->getDados();
        
        if (empty($dados)) {
            return [
                'total' => 0,
                'pendentes' => 0,
                'concluidos' => 0,
                'em_andamento' => 0,
                'taxa_conclusao' => 0,
                'ultima_atualizacao' => date('Y-m-d H:i:s'),
                'detalhes' => [
                    'pedidos_por_uf' => [],
                    'pedidos_por_cidade' => [],
                    'pedidos_por_transportadora' => [],
                    'volume_total' => 0,
                    'peso_total' => 0
                ]
            ];
        }

        $totalPedidos = count($dados);
        $pedidosPendentes = 0;
        $pedidosConcluidos = 0;
        $pedidosEmAndamento = 0;

        $pedidosPorUF = [];
        $pedidosPorCidade = [];
        $pedidosPorTransportadora = [];
        $volumeTotal = 0;
        $pesoTotal = 0;

        foreach ($dados as $linha) {
            $statusSeparacao = isset($linha['Status Separacao']) ? strtoupper(trim($linha['Status Separacao'])) : '';

            switch($statusSeparacao) {
                case 'A SEPARAR':
                    $pedidosPendentes++;
                    break;
                case 'SEPARADO':
                    $pedidosConcluidos++;
                    break;
                case 'SOLICITADO SEP':
                    $pedidosEmAndamento++;
                    break;
            }

            // Estatísticas adicionais
            if (isset($linha['UF'])) {
                $uf = trim($linha['UF']);
                $pedidosPorUF[$uf] = ($pedidosPorUF[$uf] ?? 0) + 1;
            }

            if (isset($linha['Cidade'])) {
                $cidade = trim($linha['Cidade']);
                $pedidosPorCidade[$cidade] = ($pedidosPorCidade[$cidade] ?? 0) + 1;
            }

            if (isset($linha['Transp'])) {
                $transp = trim($linha['Transp']);
                $pedidosPorTransportadora[$transp] = ($pedidosPorTransportadora[$transp] ?? 0) + 1;
            }

            if (isset($linha['Vols'])) {
                $volumeTotal += floatval(str_replace(',', '.', $linha['Vols']));
            }

            if (isset($linha['Peso Bruto'])) {
                $pesoTotal += floatval(str_replace(',', '.', $linha['Peso Bruto']));
            }
        }

        arsort($pedidosPorUF);
        arsort($pedidosPorCidade);
        arsort($pedidosPorTransportadora);

        return [
            'total' => $totalPedidos,
            'pendentes' => $pedidosPendentes,
            'concluidos' => $pedidosConcluidos,
            'em_andamento' => $pedidosEmAndamento,
            'taxa_conclusao' => ($totalPedidos > 0) ? 
                round(($pedidosConcluidos / $totalPedidos) * 100, 2) : 0,
            'ultima_atualizacao' => date('Y-m-d H:i:s'),
            'detalhes' => [
                'pedidos_por_uf' => $pedidosPorUF,
                'pedidos_por_cidade' => $pedidosPorCidade,
                'pedidos_por_transportadora' => $pedidosPorTransportadora,
                'volume_total' => round($volumeTotal, 2),
                'peso_total' => round($pesoTotal, 2)
            ]
        ];
    }

    public function getDadosFiltrados($periodo = 'dia', $dataInicio = null, $dataFim = null) {
        $dados = $this->getDados();
        $resultado = [];
        
        if (empty($dataInicio)) {
            $dataInicio = date('Y-m-d');
        }
        if (empty($dataFim)) {
            $dataFim = date('Y-m-d');
        }

        foreach ($dados as $linha) {
            $dataSeparacao = isset($linha['Solicitado Separacao']) ? 
                DateTime::createFromFormat('d/m/y H:i', $linha['Solicitado Separacao']) : null;
            
            if (!$dataSeparacao) continue;

            $dataKey = match($periodo) {
                'dia' => $dataSeparacao->format('Y-m-d'),
                'semana' => $dataSeparacao->format('Y-W'),
                'mes' => $dataSeparacao->format('Y-m'),
                'ano' => $dataSeparacao->format('Y'),
                default => $dataSeparacao->format('Y-m-d')
            };

            if (!isset($resultado[$dataKey])) {
                $resultado[$dataKey] = [
                    'pendentes' => 0,
                    'concluidos' => 0,
                    'em_andamento' => 0,
                    'volume_total' => 0,
                    'peso_total' => 0,
                    'pedidos_por_transportadora' => []
                ];
            }

            $statusSeparacao = isset($linha['Status Separacao']) ? 
                strtoupper(trim($linha['Status Separacao'])) : '';

            switch($statusSeparacao) {
                case 'A SEPARAR':
                    $resultado[$dataKey]['pendentes']++;
                    break;
                case 'SEPARADO':
                    $resultado[$dataKey]['concluidos']++;
                    break;
                case 'SOLICITADO SEP':
                    $resultado[$dataKey]['em_andamento']++;
                    break;
            }

            if (isset($linha['Vols'])) {
                $resultado[$dataKey]['volume_total'] += floatval(str_replace(',', '.', $linha['Vols']));
            }
            if (isset($linha['Peso Bruto'])) {
                $resultado[$dataKey]['peso_total'] += floatval(str_replace(',', '.', $linha['Peso Bruto']));
            }
            if (isset($linha['Transp'])) {
                $transp = trim($linha['Transp']);
                $resultado[$dataKey]['pedidos_por_transportadora'][$transp] = 
                    ($resultado[$dataKey]['pedidos_por_transportadora'][$transp] ?? 0) + 1;
            }
        }

        ksort($resultado);
        return $resultado;
    }

    public function getHistoricoImportacoes() {
        return $this->registroImportacao->getRegistros();
    }

    public function getEstatisticas($periodo) {
        return $this->registroImportacao->getEstatisticasPeriodo($periodo);
    }
}

// Processar a requisição
$painel = new PainelSeparacao();

if (isset($_POST['importar']) && isset($_FILES['arquivoImportar'])) {
    if ($painel->importarDados($_FILES['arquivoImportar'])) {
        $sucessoImportacao = true;
    } else {
        $erroImportacao = true;
    }
}

$indicadores = $painel->obterIndicadores();
$historico = $painel->getHistoricoImportacoes();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Separação</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-dashboard {
            transition: transform 0.2s;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
        }
        .modal {
            transition: opacity 0.3s ease-in-out;
        }
        .modal-content {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-boxes mr-2"></i>
                    Painel de Separação
                </h1>
                
                <div class="flex gap-4">
    <button onclick="alternarPeriodo('semanal')" class="periodo-btn px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
        <i class="fas fa-calendar-week mr-2"></i>Semanal
    </button>
    <button onclick="alternarPeriodo('mensal')" class="periodo-btn px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
        <i class="fas fa-calendar-alt mr-2"></i>Mensal
    </button>
    <button onclick="alternarPeriodo('anual')" class="periodo-btn px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600">
        <i class="fas fa-calendar mr-2"></i>Anual
    </button>
    <button onclick="abrirModal()" class="periodo-btn px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-600">
        <i class="fas fa-chart-line mr-2"></i>Análise Detalhada
    </button>
    <button id="btnAtualizacao" onclick="toggleAtualizacaoAutomatica()" class="periodo-btn px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
        <i class="fas fa-sync-alt mr-2"></i><span>Auto Atualizar</span>
    </button>
</div>
            </div>

            <!-- Formulário de Importação -->
            <form action="" method="POST" enctype="multipart/form-data" class="mb-8">
                <div class="flex items-center gap-4">
                    <input type="file" name="arquivoImportar" accept=".xlsx,.xls" 
                           class="hidden" id="arquivoInput">
                    <label for="arquivoInput" 
                           class="bg-blue-50 text-blue-500 px-4 py-2 rounded cursor-pointer hover:bg-blue-100 transition-colors flex items-center">
                        <i class="fas fa-file-upload mr-2"></i>
                        Selecionar Arquivo
                    </label>
                    <button type="submit" name="importar" 
                            class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 transition-colors flex items-center">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                        Importar
                    </button>
                </div>
            </form>

            <?php if (isset($sucessoImportacao)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <div>
                            <p class="font-bold">Dados importados com sucesso!</p>
                            <p class="text-sm">Última atualização: <?= date('d/m/Y H:i:s', strtotime($indicadores['ultima_atualizacao'])) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($erroImportacao)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                    <i class="fas fa-times-circle mr-2"></i>
                    Erro ao importar os dados.
                </div>
            <?php endif; ?>

            <!-- Grade de Indicadores -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="card-dashboard bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-blue-700 mb-2">Total de Pedidos</h3>
                            <p class="text-3xl font-bold text-blue-800"><?= $indicadores['total'] ?? 0 ?></p>
                        </div>
                        <i class="fas fa-clipboard-list text-4xl text-blue-400"></i>
                    </div>
                </div>
                
                <div class="card-dashboard bg-gradient-to-br from-yellow-50 to-yellow-100 p-6 rounded-xl shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-yellow-700 mb-2">A Separar</h3>
                            <p class="text-3xl font-bold text-yellow-800"><?= $indicadores['pendentes'] ?? 0 ?></p>
                        </div>
                        <i class="fas fa-clock text-4xl text-yellow-400"></i>
                    </div>
                </div>
                
                <div class="card-dashboard bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-green-700 mb-2">Separado</h3>
                            <p class="text-3xl font-bold text-green-800"><?= $indicadores['concluidos'] ?? 0 ?></p>
                        </div>
                        <i class="fas fa-check-circle text-4xl text-green-400"></i>
                    </div>
                </div>
                
                <div class="card-dashboard bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-xl shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-purple-700 mb-2">Solicitado Sep</h3>
                            <p class="text-3xl font-bold text-purple-800"><?= $indicadores['em_andamento'] ?? 0 ?></p>
                        </div>
                        <i class="fas fa-dolly text-4xl text-purple-400"></i>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Evolução -->
            <div class="bg-white p-6 rounded-xl shadow-md mb-8">
                <canvas id="evolutionChart"></canvas>
            </div>
                <!-- Filtros Rápidos -->
<div class="bg-white p-4 rounded-xl shadow-md mb-6">
    <div class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select id="filtroStatus" class="w-full border rounded px-3 py-2" onchange="aplicarFiltros()">
                <option value="">Todos</option>
                <option value="A SEPARAR">A Separar</option>
                <option value="SEPARADO">Separado</option>
                <option value="SOLICITADO SEP">Solicitado Sep</option>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
            <select id="filtroUF" class="w-full border rounded px-3 py-2" onchange="aplicarFiltros()">
                <option value="">Todas</option>
                <?php
                $ufs = array_keys($indicadores['detalhes']['pedidos_por_uf']);
                foreach ($ufs as $uf): ?>
                    <option value="<?= $uf ?>"><?= $uf ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Transportadora</label>
            <select id="filtroTransp" class="w-full border rounded px-3 py-2" onchange="aplicarFiltros()">
                <option value="">Todas</option>
                <?php
                $transportadoras = array_keys($indicadores['detalhes']['pedidos_por_transportadora']);
                foreach ($transportadoras as $transp): ?>
                    <option value="<?= $transp ?>"><?= $transp ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>
<!-- Sumário dos Filtros -->
<div class="mt-4 flex justify-between items-center">
    <div class="text-sm text-gray-600">
        <span id="totalFiltrado">0</span> resultados encontrados
    </div>
    <div class="w-64 bg-gray-200 rounded-full h-2.5">
        <div id="progressoFiltro" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
    </div>
</div>
            <!-- Histórico de Importações -->
            <div class="bg-white rounded-xl shadow-md overflow-x-auto mt-8">
                <h2 class="text-xl font-bold p-4 bg-gray-50 border-b">
                    <i class="fas fa-history mr-2"></i>
                    Histórico de Importações
                </h2>
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">A Separar</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Separado</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Solicitado Sep.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($historico) as $registro): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 border-b">
                                    <?= date('d/m/Y H:i:s', strtotime($registro['data'])) ?>
                                </td>
                                <td class="px-6 py-4 border-b"><?= $registro['indicadores']['total'] ?></td>
                                <td class="px-6 py-4 border-b"><?= $registro['indicadores']['pendentes'] ?></td>
                                <td class="px-6 py-4 border-b"><?= $registro['indicadores']['concluidos'] ?></td>
                                <td class="px-6 py-4 border-b"><?= $registro['indicadores']['em_andamento'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Análise Detalhada -->
    <div id="modalAnalise" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 w-11/12 max-w-6xl">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-xl font-bold text-gray-900">Análise Detalhada</h3>
                    <button onclick="fecharModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <!-- Filtros -->
                    <div class="mb-6">
                        <div class="flex gap-4 mb-4">
                            <select id="periodoAnalise" class="border rounded px-3 py-2" onchange="atualizarGraficoAnalise()">
                                <option value="dia">Por Dia</option>
                                <option value="semana">Por Semana</option>
                                <option value="mes">Por Mês</option>
                                <option value="ano">Por Ano</option>
                            </select>
                            <input type="date" id="dataInicio" class="border rounded px-3 py-2" 
                                   onchange="atualizarGraficoAnalise()" 
                                   value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                            <input type="date" id="dataFim" class="border rounded px-3 py-2" 
                                   onchange="atualizarGraficoAnalise()"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <!-- Indicadores Detalhados -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-bold mb-2">Volume Total</h4>
                            <p class="text-2xl"><?= number_format($indicadores['detalhes']['volume_total'], 2, ',', '.') ?></p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-bold mb-2">Peso Total (kg)</h4>
                            <p class="text-2xl"><?= number_format($indicadores['detalhes']['peso_total'], 2, ',', '.') ?></p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h4 class="font-bold mb-2">Taxa de Conclusão</h4>
                            <p class="text-2xl"><?= $indicadores['taxa_conclusao'] ?>%</p>
                        </div>
                    </div>

                    <!-- Gráficos -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h4 class="font-bold mb-4">Evolução Diária</h4>
                            <canvas id="graficoAnalise"></canvas>
                        </div>
                        <div>
                            <h4 class="font-bold mb-4">Distribuição por Status</h4>
                            <canvas id="graficoPizza"></canvas>
                        </div>
                    </div>

                    <!-- Análises Adicionais -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <h4 class="font-bold mb-4">Top UFs</h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php foreach (array_slice($indicadores['detalhes']['pedidos_por_uf'], 0, 5) as $uf => $total): ?>
                                    <div class="flex justify-between mb-2">
                                        <span><?= $uf ?></span>
                                        <span class="font-bold"><?= $total ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold mb-4">Top Cidades</h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php foreach (array_slice($indicadores['detalhes']['pedidos_por_cidade'], 0, 5) as $cidade => $total): ?>
                                    <div class="flex justify-between mb-2">
                                        <span><?= $cidade ?></span>
                                        <span class="font-bold"><?= $total ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold mb-4">Top Transportadoras</h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php foreach (array_slice($indicadores['detalhes']['pedidos_por_transportadora'], 0, 5) as $transp => $total): ?>
                                    <div class="flex justify-between mb-2">
                                        <span><?= $transp ?></span>
                                        <span class="font-bold"><?= $total ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Exportação -->
    <div id="modalExportacao" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 w-96">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-xl font-bold text-gray-900">Exportar Dados</h3>
                    <button onclick="fecharModalExportacao()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                <div class="space-y-4">
                        <button onclick="exportarDados('atual')" 
                                class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 flex items-center justify-center">
                            <i class="fas fa-file-excel mr-2"></i>
                            Exportar Dados Atuais
                        </button>
                        <button onclick="exportarDados('historico')" 
                                class="w-full bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 flex items-center justify-center">
                            <i class="fas fa-history mr-2"></i>
                            Exportar Histórico Completo
                        </button>
                        <div class="border-t pt-4">
                            <h4 class="font-semibold mb-2">Exportar por Período</h4>
                            <div class="space-y-2">
                                <input type="date" id="exportDataInicio" class="w-full border rounded px-3 py-2" 
                                       value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                                <input type="date" id="exportDataFim" class="w-full border rounded px-3 py-2" 
                                       value="<?= date('Y-m-d') ?>">
                                <button onclick="exportarDados('periodo')" 
                                        class="w-full bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 flex items-center justify-center">
                                    <i class="fas fa-calendar-alt mr-2"></i>
                                    Exportar Período Selecionado
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mensagemSucesso = document.querySelector('.bg-green-100');
            if (mensagemSucesso) {
                setTimeout(() => {
                    mensagemSucesso.style.display = 'none';
                }, 3000);
            }

            const inputArquivo = document.getElementById('arquivoInput');
            inputArquivo.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name;
                if (fileName) {
                    this.nextElementSibling.textContent = fileName;
                }
            });

            atualizarGrafico('semanal');
            inicializarGraficoPizza();
        });

        function abrirModal() {
            document.getElementById('modalAnalise').classList.remove('hidden');
            atualizarGraficoAnalise();
        }

        function fecharModal() {
            document.getElementById('modalAnalise').classList.add('hidden');
        }

        function abrirModalExportacao() {
            document.getElementById('modalExportacao').classList.remove('hidden');
        }

        function fecharModalExportacao() {
            document.getElementById('modalExportacao').classList.add('hidden');
        }

        function exportarDados(tipo) {
            let url = 'exportar.php?tipo=' + tipo;
            
            if (tipo === 'periodo') {
                const dataInicio = document.getElementById('exportDataInicio').value;
                const dataFim = document.getElementById('exportDataFim').value;
                url += '&dataInicio=' + dataInicio + '&dataFim=' + dataFim;
            }
            
            window.location.href = url;
        }

        function inicializarGraficoPizza() {
            const ctx = document.getElementById('graficoPizza').getContext('2d');
            const data = {
                labels: ['A Separar', 'Separado', 'Solicitado Sep'],
                datasets: [{
                    data: [
                        <?= $indicadores['pendentes'] ?>,
                        <?= $indicadores['concluidos'] ?>,
                        <?= $indicadores['em_andamento'] ?>
                    ],
                    backgroundColor: [
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(168, 85, 247, 0.8)'
                    ],
                    borderWidth: 1
                }]
            };

            new Chart(ctx, {
                type: 'pie',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Distribuição de Status'
                        }
                    }
                }
            });
        }

        function alternarPeriodo(periodo) {
            atualizarGrafico(periodo);
            document.querySelectorAll('.periodo-btn').forEach(btn => {
                btn.classList.remove('bg-opacity-80');
            });
            event.target.closest('button').classList.add('bg-opacity-80');
        }

        function atualizarGrafico(periodo) {
            fetch(`get_estatisticas.php?periodo=${periodo}`)
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('evolutionChart').getContext('2d');
                    
                    if (window.myChart) {
                        window.myChart.destroy();
                    }

                    const periodoTexto = {
                        'semanal': 'Semanal',
                        'mensal': 'Mensal',
                        'anual': 'Anual'
                    }[periodo];

                    window.myChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: Object.keys(data),
                            datasets: [
                                {
                                    label: 'Total de Pedidos',
                                    data: Object.values(data).map(d => d.total_pedidos),
                                    borderColor: 'rgb(59, 130, 246)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                },
                                {
                                    label: 'A Separar',
                                    data: Object.values(data).map(d => d.pendentes),
                                    borderColor: 'rgb(234, 179, 8)',
                                    backgroundColor: 'rgba(234, 179, 8, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                },
                                {
                                    label: 'Separado',
                                    data: Object.values(data).map(d => d.concluidos),
                                    borderColor: 'rgb(34, 197, 94)',
                                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                },
                                {
                                    label: 'Solicitado Sep',
                                    data: Object.values(data).map(d => d.em_andamento),
                                    borderColor: 'rgb(168, 85, 247)',
                                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                },
                                title: {
                                    display: true,
                                    text: `Evolução dos Pedidos - Visão ${periodoTexto}`,
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    },
                                    padding: {
                                        top: 10,
                                        bottom: 30
                                    }
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        drawBorder: false,
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Erro ao carregar dados:', error));
        }

        function atualizarGraficoAnalise() {
            const periodo = document.getElementById('periodoAnalise').value;
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;

            fetch(`get_dados_filtrados.php?periodo=${periodo}&dataInicio=${dataInicio}&dataFim=${dataFim}`)
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('graficoAnalise').getContext('2d');
                    
                    if (window.graficoAnalise) {
                        window.graficoAnalise.destroy();
                    }

                    window.graficoAnalise = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(data),
                            datasets: [
                                {
                                    label: 'A Separar',
                                    data: Object.values(data).map(d => d.pendentes),
                                    backgroundColor: 'rgba(234, 179, 8, 0.5)',
                                    borderColor: 'rgb(234, 179, 8)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Separado',
                                    data: Object.values(data).map(d => d.concluidos),
                                    backgroundColor: 'rgba(34, 197, 94, 0.5)',
                                    borderColor: 'rgb(34, 197, 94)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Solicitado Sep',
                                    data: Object.values(data).map(d => d.em_andamento),
                                    backgroundColor: 'rgba(168, 85, 247, 0.5)',
                                    borderColor: 'rgb(168, 85, 247)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Análise por Período'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    stacked: true
                                },
                                x: {
                                    stacked: true
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Erro ao carregar dados:', error));
        }
    </script>
    function aplicarFiltros() {
    const status = document.getElementById('filtroStatus').value;
    const uf = document.getElementById('filtroUF').value;
    const transp = document.getElementById('filtroTransp').value;

    const linhas = document.querySelectorAll('table tbody tr');
    let totalVisivel = 0;
    const totalLinhas = linhas.length;

    linhas.forEach(linha => {
        const statusLinha = linha.children[2].textContent.trim();
        const ufLinha = linha.children[1].textContent.trim().split(' - ')[0];
        const transpLinha = linha.children[3].textContent.trim();

        const exibir = (!status || statusLinha === status) &&
                      (!uf || ufLinha === uf) &&
                      (!transp || transpLinha === transp);

        linha.style.display = exibir ? '' : 'none';
        if (exibir) totalVisivel++;
    });

    // Atualiza contador e barra de progresso
    document.getElementById('totalFiltrado').textContent = totalVisivel;
    const percentual = (totalVisivel / totalLinhas) * 100;
    document.getElementById('progressoFiltro').style.width = `${percentual}%`;
    
    // Atualiza cor da barra baseado no percentual
    const elemento = document.getElementById('progressoFiltro');
    if (percentual < 30) {
        elemento.classList.remove('bg-blue-600', 'bg-green-600');
        elemento.classList.add('bg-red-600');
    } else if (percentual < 70) {
        elemento.classList.remove('bg-red-600', 'bg-green-600');
        elemento.classList.add('bg-blue-600');
    } else {
        elemento.classList.remove('bg-red-600', 'bg-blue-600');
        elemento.classList.add('bg-green-600');
    }
}

// Executa filtro inicial ao carregar
document.addEventListener('DOMContentLoaded', function() {
    aplicarFiltros();
    // ... resto do código existente ...
});
</body>
</html>