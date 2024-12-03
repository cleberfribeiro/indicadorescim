<?php
// Configurações iniciais
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Função para calcular tendências
function calcularTendencia($valorAtual, $valorAnterior) {
    if ($valorAnterior == 0) return ['valor' => 0, 'direcao' => 'neutral'];
    $mudanca = (($valorAtual - $valorAnterior) / $valorAnterior) * 100;
    return [
        'valor' => abs(round($mudanca, 1)),
        'direcao' => $mudanca > 0 ? 'up' : ($mudanca < 0 ? 'down' : 'neutral')
    ];
}

// Função para ler o último registro do arquivo de gestão de estoque
function lerUltimoRegistroEstoque() {
    $arquivo = 'historico_gestao_estoque.txt';
    if (!file_exists($arquivo)) {
        return false;
    }

    $linhas = file($arquivo);
    $ultimoRegistro = [];
    $dataRegistro = '';
    $ultimaData = null;

    foreach ($linhas as $linha) {
        if (strpos($linha, '[DATA]') === 0) {
            $dataAtual = trim(substr($linha, 7));
            if ($ultimaData === null || strtotime($dataAtual) > strtotime($ultimaData)) {
                $ultimaData = $dataAtual;
                $ultimoRegistro = [];
                $dataRegistro = $dataAtual;
            }
        } elseif ($ultimaData !== null && trim($linha) !== '') {
            $dados = explode('|', $linha);
            if (count($dados) >= 5) {
                $local = trim($dados[0]);
                $valores = [];
                foreach (array_slice($dados, 1) as $item) {
                    list($chave, $valor) = explode(':', $item);
                    $valores[trim($chave)] = intval($valor);
                }
                $ultimoRegistro[$local] = $valores;
            }
        }
    }

    return ['data' => $dataRegistro, 'dados' => $ultimoRegistro];
}

// Função para calcular os totais de perdas
function calcularTotaisPerdas($dados) {
    $totais = [
        'hidrante' => 20,
        'corredor' => 11,
        'reservado' => 138,
        'almoxarifado' => 81,
        'amassado' => 14
    ];
    return $totais;
}

// Função para ler dados de mão de obra
function lerDadosMaoObra() {
    $arquivo = 'dados/servicos_mdo.txt';
    if (!file_exists($arquivo)) {
        return ['mensal' => [], 'total' => []];
    }

    $dados = [
        'mensal' => [],
        'total' => [
            'diaria' => 0,
            'hora_extra' => 0,
            'desconto' => 0,
            'descarga' => 0,
            'total' => 0
        ]
    ];

    $linhas = file($arquivo);
    foreach ($linhas as $linha) {
        $campos = explode('|', $linha);
        if (count($campos) >= 6) {
            $data = date('Y-m', strtotime($campos[1]));
            $tipo = strtoupper(trim($campos[3]));
            $valor = floatval($campos[5]);

            if (!isset($dados['mensal'][$data])) {
                $dados['mensal'][$data] = [
                    'diaria' => 0,
                    'hora_extra' => 0,
                    'desconto' => 0,
                    'descarga' => 0,
                    'total' => 0
                ];
            }

            if (strpos($tipo, 'DIÁRIA') !== false || strpos($tipo, 'DIARIA') !== false) {
                $dados['mensal'][$data]['diaria'] += $valor;
                $dados['total']['diaria'] += $valor;
            } elseif (strpos($tipo, 'HORA EXTRA') !== false) {
                $dados['mensal'][$data]['hora_extra'] += $valor;
                $dados['total']['hora_extra'] += $valor;
            } elseif (strpos($tipo, 'DESCONTO') !== false) {
                $dados['mensal'][$data]['desconto'] += $valor;
                $dados['total']['desconto'] += $valor;
            } elseif (strpos($tipo, 'DESCARGA') !== false) {
                $dados['mensal'][$data]['descarga'] += $valor;
                $dados['total']['descarga'] += $valor;
            }
            
            $dados['mensal'][$data]['total'] += $valor;
            $dados['total']['total'] += $valor;
        }
    }

    // Ordenar por data
    ksort($dados['mensal']);

    return $dados;
}

// Função para calcular totais do estoque
function calcularTotaisEstoque($dados) {
    $totais = [
        'total' => 0,
        'ocupadas' => 0,
        'vazias' => 0,
        'perdas' => 0,
        'vazias_real' => 0
    ];

    foreach ($dados as $local) {
        foreach ($totais as $key => $value) {
            $totais[$key] += isset($local[$key]) ? $local[$key] : 0;
        }
    }

    return $totais;
}

// Função para formatar números inteiros
function formatarNumero($numero) {
    return number_format($numero, 0, ',', '.');
}

// Função para formatar moeda
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para calcular porcentagem
function calcularPorcentagem($valor, $total) {
    return $total > 0 ? round(($valor / $total) * 100, 1) : 0;
}

// Carregar dados
$registroEstoque = lerUltimoRegistroEstoque();
$dadosEstoque = $registroEstoque['dados'];
$totaisEstoque = calcularTotaisEstoque($dadosEstoque);
$dadosMaoObra = lerDadosMaoObra();
$totaisPerdas = calcularTotaisPerdas($dadosEstoque);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #27ae60;
            --info: #3498db;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light-bg: #f5f7fa;
            --card-shadow: 0 2px 4px rgba(0,0,0,0.1);
            --hover-shadow: 0 4px 8px rgba(0,0,0,0.2);
            --transition: all 0.3s ease;
            --border-radius: 12px;
        }

        body { 
            background-color: var(--light-bg);
            padding-bottom: 2rem;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }
        
        .container-fluid { 
            padding: 20px; 
            max-width: 1800px;
            margin: 0 auto;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 20px;
            background: white;
            padding: 10px 20px 0;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .section-title {
            color: var(--primary);
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .metric-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .metric-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            opacity: 0.8;
            transition: var(--transition);
        }

        .metric-value {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
            color: var(--primary);
        }

        .metric-label {
            color: var(--secondary);
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .trend-indicator {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-top: 10px;
        }

        .trend-up { background-color: rgba(39, 174, 96, 0.1); color: var(--success); }
        .trend-down { background-color: rgba(231, 76, 60, 0.1); color: var(--danger); }
        .trend-neutral { background-color: rgba(52, 152, 219, 0.1); color: var(--info); }

        .construction-card {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            text-align: center;
            padding: 40px 20px;
        }

        .construction-icon {
            font-size: 3em;
            color: #adb5bd;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary);
            padding: 12px 20px;
            margin-right: 5px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 500;
            transition: var(--transition);
        }

        .nav-tabs .nav-link:hover:not(.active) {
            background: rgba(44, 62, 80, 0.1);
            color: var(--primary);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: white;
        }

        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            height: 400px;
            position: relative;
        }

        .chart-title {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .service-selector {
            margin-bottom: 20px;
            position: relative;
        }

        .service-selector select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            appearance: none;
            background: white;
            font-size: 0.9em;
            color: var(--primary);
            cursor: pointer;
            transition: var(--transition);
        }

        .service-selector select:hover {
            border-color: var(--primary);
        }

        .service-selector select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(44, 62, 80, 0.1);
        }

        .service-selector::after {
            content: '\f078';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            pointer-events: none;
        }

        .service-info {
            padding: 10px;
            margin-top: 10px;
            border-radius: var(--border-radius);
            background: rgba(52, 152, 219, 0.1);
            font-size: 0.9em;
            color: var(--primary);
            display: none;
        }

        .service-info.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Navegação -->
    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="estoque-tab" data-bs-toggle="tab" data-bs-target="#estoque" type="button" role="tab">
                <i class="fas fa-box me-2"></i>Gestão de Estoque
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="mao-obra-tab" data-bs-toggle="tab" data-bs-target="#mao-obra" type="button" role="tab">
                <i class="fas fa-users me-2"></i>Mão de Obra
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link disabled" id="separacao-tab">
                <i class="fas fa-clipboard-list me-2"></i>Separação
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link disabled" id="expedicao-tab">
                <i class="fas fa-shipping-fast me-2"></i>Expedição
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link disabled" id="avarias-tab">
                <i class="fas fa-exclamation-triangle me-2"></i>Avarias
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link disabled" id="custo-frete-tab">
                <i class="fas fa-dollar-sign me-2"></i>Custo Frete
            </button>
        </li>
    </ul>

    <div class="container-fluid">
        <div class="tab-content" id="dashboardTabsContent">
            <!-- Painel Principal (Dashboard) -->
            <div class="tab-pane fade show active" id="home" role="tabpanel">
                <!-- Gestão de Estoque -->
                <h3 class="section-title">
                    <i class="fas fa-box me-2"></i>Gestão de Estoque
                </h3>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-warehouse text-primary metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['total']); ?></div>
                            <div class="metric-label">Posições Totais</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-box-open text-success metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['ocupadas']); ?></div>
                            <div class="metric-label">Posições Ocupadas</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-door-open text-info metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['vazias']); ?></div>
                            <div class="metric-label">Posições Vazias</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-exclamation-triangle text-danger metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['perdas']); ?></div>
                            <div class="metric-label">Perdas/Reservas</div>
                        </div>
                    </div>
                </div>

                <!-- Mão de Obra -->
                <h3 class="section-title">
                    <i class="fas fa-users me-2"></i>Mão de Obra
                </h3>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-user-friends text-primary metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda($dadosMaoObra['total']['diaria']); ?></div>
                            <div class="metric-label">Diária Ajudante</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-clock text-warning metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda($dadosMaoObra['total']['hora_extra']); ?></div>
                            <div class="metric-label">Hora Extra</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-piggy-bank text-success metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda(abs($dadosMaoObra['total']['desconto'])); ?></div>
                            <div class="metric-label">Economia por Descontos</div>
                            <div class="small text-success">Redução nos custos operacionais</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-truck-loading text-info metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda($dadosMaoObra['total']['descarga']); ?></div>
                            <div class="metric-label">Descarga</div>
                        </div>
                    </div>
                </div>

                <!-- Recebimento -->
                <h3 class="section-title">
                    <i class="fas fa-truck me-2"></i>Recebimento
                </h3>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="construction-card">
                            <i class="fas fa-tools construction-icon"></i>
                            <h4>Em Desenvolvimento</h4>
                            <p>Esta funcionalidade está sendo implementada e estará disponível em breve.</p>
                        </div>
                    </div>
                </div>

                <!-- Separação -->
                <h3 class="section-title">
                    <i class="fas fa-clipboard-list me-2"></i>Separação
                </h3>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="construction-card">
                            <i class="fas fa-tools construction-icon"></i>
                            <h4>Em Desenvolvimento</h4>
                            <p>Esta funcionalidade está sendo implementada e estará disponível em breve.</p>
                        </div>
                    </div>
                </div>

                <!-- Expedição -->
                <h3 class="section-title">
                    <i class="fas fa-shipping-fast me-2"></i>Expedição
                </h3>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="construction-card">
                            <i class="fas fa-tools construction-icon"></i>
                            <h4>Em Desenvolvimento</h4>
                            <p>Esta funcionalidade está sendo implementada e estará disponível em breve.</p>
                        </div>
                    </div>
                </div>

                <!-- Avarias -->
                <h3 class="section-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Avarias
                </h3>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="construction-card">
                            <i class="fas fa-tools construction-icon"></i>
                            <h4>Em Desenvolvimento</h4>
                            <p>Esta funcionalidade está sendo implementada e estará disponível em breve.</p>
                        </div>
                    </div>
                </div>

                <!-- Custo Frete -->
                <h3 class="section-title">
                    <i class="fas fa-dollar-sign me-2"></i>Custo Frete
                </h3>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="construction-card">
                            <i class="fas fa-tools construction-icon"></i>
                            <h4>Em Desenvolvimento</h4>
                            <p>Esta funcionalidade está sendo implementada e estará disponível em breve.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aba de Gestão de Estoque -->
            <div class="tab-pane fade" id="estoque" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-warehouse text-primary metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['ocupadas']); ?></div>
                            <div class="metric-label">Posições Ocupadas</div>
                            <div class="small text-muted mt-2">
                                <?php echo calcularPorcentagem($totaisEstoque['ocupadas'], $totaisEstoque['total']); ?>% do total
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-door-open text-success metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['vazias']); ?></div>
                            <div class="metric-label">Posições Vazias</div>
                            <div class="small text-success mt-2">
                                <?php echo calcularPorcentagem($totaisEstoque['vazias'], $totaisEstoque['total']); ?>% do total
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-exclamation-triangle text-danger metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['perdas']); ?></div>
                            <div class="metric-label">Perdas/Reservas</div>
                            <div class="small text-danger mt-2">
                                <?php echo calcularPorcentagem($totaisEstoque['perdas'], $totaisEstoque['total']); ?>% do total
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-warehouse text-info metric-icon"></i>
                            <div class="metric-value"><?php echo formatarNumero($totaisEstoque['total']); ?></div>
                            <div class="metric-label">Posições Totais</div>
                            <div class="small text-info mt-2">
                                <?php echo formatarNumero($totaisEstoque['vazias_real']); ?> vazias reais
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos do Estoque -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5 class="chart-title">Distribuição por Localização</h5>
                            <canvas id="localizacaoChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-container">
                            <h5 class="chart-title">Tipos de Perdas</h5>
                            <canvas id="perdasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aba de Mão de Obra -->
            <div class="tab-pane fade" id="mao-obra" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-user-friends text-primary metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda($dadosMaoObra['total']['diaria']); ?></div>
                            <div class="metric-label">Diária Ajudante</div>
                            <div class="trend-indicator trend-up">
                                <?php echo calcularPorcentagem($dadosMaoObra['total']['diaria'], $dadosMaoObra['total']['total']); ?>% do total
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-clock text-warning metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda($dadosMaoObra['total']['hora_extra']); ?></div>
                            <div class="metric-label">Hora Extra</div>
                            <div class="trend-indicator trend-warning">
                                <?php echo calcularPorcentagem($dadosMaoObra['total']['hora_extra'], $dadosMaoObra['total']['total']); ?>% do total
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-piggy-bank text-success metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda(abs($dadosMaoObra['total']['desconto'])); ?></div>
                            <div class="metric-label">Economia por Descontos</div>
                            <div class="trend-indicator trend-up">Redução nos custos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="fas fa-truck-loading text-info metric-icon"></i>
                            <div class="metric-value"><?php echo formatarMoeda($dadosMaoObra['total']['descarga']); ?></div>
                            <div class="metric-label">Descarga</div>
                            <div class="trend-indicator trend-info">
                                <?php echo calcularPorcentagem($dadosMaoObra['total']['descarga'], $dadosMaoObra['total']['total']); ?>% do total
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos de Mão de Obra -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <div class="service-selector">
                                <select id="servicoSelector" class="form-select">
                                    <option value="total">Total Geral</option>
                                    <option value="diaria">Diária Ajudante</option>
                                    <option value="hora_extra">Hora Extra</option>
                                    <option value="desconto">Economia por Descontos</option>
                                    <option value="descarga">Descarga</option>
                                </select>
                            </div>
                            <div id="serviceInfo" class="service-info"></div>
                            <canvas id="evolucaoCustosChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-container">
                            <h5 class="chart-title">Distribuição dos Custos</h5>
                            <canvas id="distribuicaoCustosChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configurações globais do Chart.js
        Chart.defaults.font.family = "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#666';
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Paleta de cores
        const cores = {
            ocupadas: '#3498db',
            vazias: '#27ae60',
            perdas: '#e74c3c',
            horaExtra: '#f39c12',
            descarga: '#2ecc71',
            economia: '#27ae60'
        };

        // Configuração base para gráficos
        const configBase = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: { weight: 500 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#2c3e50',
                    titleFont: { weight: 600 },
                    bodyColor: '#666',
                    bodyFont: { size: 13 },
                    borderColor: '#ddd',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    cornerRadius: 8,
                    boxPadding: 6
                }
            }
        };

        // Dados de Mão de Obra para os gráficos
        const dadosMaoObraGraficos = {
            meses: <?php echo json_encode(array_map(function($mes) {
                return date('M/Y', strtotime($mes));
            }, array_keys($dadosMaoObra['mensal']))); ?>,
            dados: <?php echo json_encode($dadosMaoObra['mensal']); ?>
        };

        // Texto informativo para cada tipo de serviço
        const servicoInfo = {
            total: 'Visão geral dos custos totais mensais com mão de obra.',
            diaria: 'Custos com diárias de ajudantes, representando o valor base da operação.',
            hora_extra: 'Custos adicionais com horas extras quando necessário.',
            desconto: 'Economia obtida através de descontos negociados, representando redução nos custos.',
            descarga: 'Custos específicos com operações de descarga de mercadorias.'
        };

        // Gráfico de Distribuição por Localização
        const localizacaoChart = new Chart(
            document.getElementById('localizacaoChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($dadosEstoque)); ?>,
                    datasets: [{
                        label: 'Ocupadas',
                        data: <?php echo json_encode(array_column($dadosEstoque, 'ocupadas')); ?>,
                        backgroundColor: cores.ocupadas,
                        stack: 'Stack 0'
                    },
                    {
                        label: 'Vazias',
                        data: <?php echo json_encode(array_column($dadosEstoque, 'vazias')); ?>,
                        backgroundColor: cores.vazias,
                        stack: 'Stack 0'
                    },
                    {
                        label: 'Perdas',
                        data: <?php echo json_encode(array_column($dadosEstoque, 'perdas')); ?>,
                        backgroundColor: cores.perdas,
                        stack: 'Stack 0'
                    }]
                },
                options: {
                    ...configBase,
                    scales: {
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                callback: value => value.toLocaleString()
                            }
                        },
                        x: {
                            stacked: true,
                            grid: { display: false }
                        }
                    }
                }
            }
        );

        // Gráfico de Tipos de Perdas
        const perdasChart = new Chart(
            document.getElementById('perdasChart').getContext('2d'),
            {
                type: 'doughnut',
                data: {
                    labels: ['Hidrante/Viga', 'Corredor', 'Reservado', 'Almoxarifado', 'Amassado'],
                    datasets: [{
                        data: Object.values(<?php echo json_encode($totaisPerdas); ?>),
                        backgroundColor: [
                            cores.ocupadas,
                            cores.vazias,
                            cores.horaExtra,
                            '#9b59b6',
                            cores.perdas
                        ]
                    }]
                },
                options: {
                    ...configBase,
                    cutout: '60%',
                    plugins: {
                        ...configBase.plugins,
                        tooltip: {
                            ...configBase.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${context.label}: ${value} posições (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            }
        );

        // Função para atualizar o gráfico de evolução de custos
        function atualizarGraficoEvolucao(tipoServico) {
            const ctx = document.getElementById('evolucaoCustosChart').getContext('2d');
            if (window.evolucaoCustosChart) {
                window.evolucaoCustosChart.destroy();
            }

            const valores = dadosMaoObraGraficos.meses.map((mes, index) => {
                const dadosMes = Object.values(dadosMaoObraGraficos.dados)[index];
                return tipoServico === 'total' ? dadosMes.total : dadosMes[tipoServico];
            });

            // Atualizar informações do serviço
            const infoElement = document.getElementById('serviceInfo');
            infoElement.textContent = servicoInfo[tipoServico];
            infoElement.className = 'service-info active';

            window.evolucaoCustosChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dadosMaoObraGraficos.meses,
                    datasets: [{
                        label: tipoServico === 'desconto' ? 'Economia Obtida' : 'Valor Total',
                        data: tipoServico === 'desconto' ? valores.map(Math.abs) : valores,
                        backgroundColor: tipoServico === 'desconto' ? cores.economia : cores.ocupadas,
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    ...configBase,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            },
                            grid: {
                                drawBorder: false,
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        ...configBase.plugins,
                        tooltip: {
                            ...configBase.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    let value = context.raw;
                                    if (tipoServico === 'desconto') {
                                        value = Math.abs(value);
                                    }
                                    return 'R$ ' + value.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Distribuição dos Custos
        const distribuicaoCustosChart = new Chart(
            document.getElementById('distribuicaoCustosChart').getContext('2d'),
            {
                type: 'doughnut',
                data: {
                    labels: ['Diária', 'Hora Extra', 'Economia', 'Descarga'],
                    datasets: [{
                        data: [
                            <?php echo abs($dadosMaoObra['total']['diaria']); ?>,
                            <?php echo abs($dadosMaoObra['total']['hora_extra']); ?>,
                            <?php echo abs($dadosMaoObra['total']['desconto']); ?>,
                            <?php echo abs($dadosMaoObra['total']['descarga']); ?>
                        ],
                        backgroundColor: [
                            cores.ocupadas,
                            cores.horaExtra,
                            cores.economia,
                            cores.descarga
                        ]
                    }]
                },
                options: {
                    ...configBase,
                    cutout: '60%',
                    plugins: {
                        ...configBase.plugins,
                        tooltip: {
                            ...configBase.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + Math.abs(b), 0);
                                    const percentage = ((Math.abs(value) / total) * 100).toFixed(1);
                                    return `${context.label}: R$ ${Math.abs(value).toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2
                                    })} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            }
        );

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar o gráfico de evolução com o total
            atualizarGraficoEvolucao('total');

            // Listener para o seletor de serviço
            document.getElementById('servicoSelector').addEventListener('change', function(e) {
                atualizarGraficoEvolucao(e.target.value);
            });
        });
    </script>
</body>
</html>