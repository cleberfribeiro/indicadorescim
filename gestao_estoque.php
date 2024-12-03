<?php
// Ajusta o fuso horário para São Paulo/Brasil
date_default_timezone_set('America/Sao_Paulo');

class EstoqueManager {
    private $localizacoes = [];
    private $perdas = [];
    private $arquivoGestao = 'historico_gestao_estoque.txt';
    private $arquivoPerdas = 'historico_perdas_endereco.txt';

    public function __construct() {
        // Cria pasta de logs se não existir
        if (!file_exists('logs')) {
            mkdir('logs', 0777, true);
        }
        
        // Carrega os dados mais recentes dos arquivos
        $this->carregarUltimosRegistros();
    }

    private function carregarUltimosRegistros() {
        // Carrega dados da gestão de estoque
        if (file_exists($this->arquivoGestao)) {
            $conteudo = file_get_contents($this->arquivoGestao);
            if ($conteudo) {
                // Divide o conteúdo em blocos por linha em branco
                $blocos = array_filter(explode("\n\n", $conteudo));
                
                if (!empty($blocos)) {
                    // Pega o último bloco
                    $ultimoBloco = trim(end($blocos));
                    $linhas = explode("\n", $ultimoBloco);
                    
                    $dados = [];
                    foreach ($linhas as $linha) {
                        $linha = trim($linha);
                        if (empty($linha) || strpos($linha, '[DATA]') === 0) continue;
                        
                        if (strpos($linha, 'A') === 0) {
                            $partes = explode('|', $linha);
                            $loc = $partes[0];
                            $dados[$loc] = [];
                            
                            // Processa cada campo após a localização
                            for ($i = 1; $i < count($partes); $i++) {
                                list($campo, $valor) = explode(':', $partes[$i]);
                                $dados[$loc][$campo] = (int)$valor;
                            }
                        }
                    }
                    
                    if (!empty($dados)) {
                        $this->localizacoes = $dados;
                    }
                }
            }
        }

        // Se não encontrou dados, usa valores padrão
        if (empty($this->localizacoes)) {
            $this->localizacoes = [
                'A1' => ['total' => 1986, 'ocupadas' => 0, 'vazias' => 0, 'perdas' => 27, 'vazias_real' => 157],
                'A2' => ['total' => 564, 'ocupadas' => 0, 'vazias' => 0, 'perdas' => 20, 'vazias_real' => 110],
                'A3' => ['total' => 1354, 'ocupadas' => 0, 'vazias' => 0, 'perdas' => 130, 'vazias_real' => 275],
                'A4/A5' => ['total' => 610, 'ocupadas' => 0, 'vazias' => 0, 'perdas' => 87, 'vazias_real' => 273]
            ];
        }

        // Carrega dados das perdas
        if (file_exists($this->arquivoPerdas)) {
            $conteudo = file_get_contents($this->arquivoPerdas);
            if ($conteudo) {
                $blocos = array_filter(explode("\n\n", $conteudo));
                
                if (!empty($blocos)) {
                    $ultimoBloco = trim(end($blocos));
                    $linhas = explode("\n", $ultimoBloco);
                    
                    $dados = [];
                    foreach ($linhas as $linha) {
                        $linha = trim($linha);
                        if (empty($linha) || strpos($linha, '[DATA]') === 0) continue;
                        
                        if (strpos($linha, 'A') === 0) {
                            $partes = explode('|', $linha);
                            $loc = $partes[0];
                            $dados[$loc] = [];
                            
                            for ($i = 1; $i < count($partes); $i++) {
                                list($campo, $valor) = explode(':', $partes[$i]);
                                $dados[$loc][$campo] = (int)$valor;
                            }
                        }
                    }
                    
                    if (!empty($dados)) {
                        $this->perdas = $dados;
                    }
                }
            }
        }

        // Se não encontrou dados das perdas, usa valores padrão
        if (empty($this->perdas)) {
            $this->perdas = [
                'A1' => ['hidrante' => 2, 'corredor' => 5, 'reservado' => 12, 'almoxarifado' => 0, 'amassado' => 8],
                'A2' => ['hidrante' => 18, 'corredor' => 2, 'reservado' => 0, 'almoxarifado' => 0, 'amassado' => 0],
                'A3' => ['hidrante' => 0, 'corredor' => 4, 'reservado' => 126, 'almoxarifado' => 0, 'amassado' => 0],
                'A4/A5' => ['hidrante' => 0, 'corredor' => 0, 'reservado' => 0, 'almoxarifado' => 81, 'amassado' => 6]
            ];
        }

        $this->recalcularValores();
    }

    private function salvarHistorico() {
        $dataHora = date('d/m/Y H:i:s');
        
        // Salva histórico da gestão de estoque
        $conteudoGestao = "[DATA] " . $dataHora . "\n";
        foreach ($this->localizacoes as $loc => $dados) {
            $linha = $loc;
            foreach ($dados as $campo => $valor) {
                $linha .= "|$campo:$valor";
            }
            $conteudoGestao .= $linha . "\n";
        }
        $conteudoGestao .= "\n";
        
        file_put_contents($this->arquivoGestao, $conteudoGestao, FILE_APPEND);
        
        // Salva histórico das perdas
        $conteudoPerdas = "[DATA] " . $dataHora . "\n";
        foreach ($this->perdas as $loc => $dados) {
            $linha = $loc;
            foreach ($dados as $campo => $valor) {
                $linha .= "|$campo:$valor";
            }
            $conteudoPerdas .= $linha . "\n";
        }
        $conteudoPerdas .= "\n";
        
        file_put_contents($this->arquivoPerdas, $conteudoPerdas, FILE_APPEND);
    }

    public function recalcularValores() {
        foreach ($this->localizacoes as $loc => &$dados) {
            // Atualiza perdas totais por localização
            $dados['perdas'] = array_sum($this->perdas[$loc]);
            
            // =I3-G3 -> VAZIAS (vazias_real - perdas)
            $dados['vazias'] = $dados['vazias_real'] - $dados['perdas'];
            
            // =H3-I3 -> POSIÇÕES OCUPADAS (total - vazias_real)
            $dados['ocupadas'] = $dados['total'] - $dados['vazias_real'];
        }
    }

    // Continua...
public function calcularPorcentagens($localizacao) {
        $dados = $this->localizacoes[$localizacao];
        return [
            'ocupada' => $dados['total'] > 0 ? round(($dados['ocupadas'] / $dados['total']) * 100, 1) : 0,
            'vazia' => $dados['total'] > 0 ? round((($dados['vazias_real'] / $dados['total']) - ($dados['perdas'] / $dados['total'])) * 100, 1) : 0,
            'perda' => $dados['total'] > 0 ? round(($dados['perdas'] / $dados['total']) * 100, 1) : 0
        ];
    }

    public function getTotais() {
        $totais = [
            'ocupadas' => 0,
            'vazias' => 0,
            'perdas' => 0,
            'total' => 0,
            'vazias_real' => 0
        ];

        foreach ($this->localizacoes as $dados) {
            $totais['ocupadas'] += $dados['ocupadas'];
            $totais['vazias'] += $dados['vazias'];
            $totais['perdas'] += $dados['perdas'];
            $totais['total'] += $dados['total'];
            $totais['vazias_real'] += $dados['vazias_real'];
        }

        return $totais;
    }

    public function getTotaisPerdas() {
        $totais = [
            'hidrante' => 0,
            'corredor' => 0,
            'reservado' => 0,
            'almoxarifado' => 0,
            'amassado' => 0,
            'total' => 0
        ];

        foreach ($this->perdas as $local) {
            $totais['hidrante'] += $local['hidrante'];
            $totais['corredor'] += $local['corredor'];
            $totais['reservado'] += $local['reservado'];
            $totais['almoxarifado'] += $local['almoxarifado'];
            $totais['amassado'] += $local['amassado'];
        }

        $totais['total'] = $totais['hidrante'] + $totais['corredor'] + 
                          $totais['reservado'] + $totais['almoxarifado'] + 
                          $totais['amassado'];

        return $totais;
    }

    public function getDados() {
        return $this->localizacoes;
    }

    public function getPerdas() {
        return $this->perdas;
    }

    public function atualizarPosicao($localizacao, $campo, $valor) {
        if (isset($this->localizacoes[$localizacao])) {
            $this->localizacoes[$localizacao][$campo] = (int)$valor;
            $this->recalcularValores();
            $this->salvarHistorico();
            
            return [
                'valores' => $this->localizacoes[$localizacao],
                'porcentagens' => $this->calcularPorcentagens($localizacao),
                'totais' => $this->getTotais(),
                'mensagem' => 'Dados salvos com sucesso!'
            ];
        }
        return false;
    }

    public function atualizarPerda($localizacao, $campo, $valor) {
        if (isset($this->perdas[$localizacao])) {
            $this->perdas[$localizacao][$campo] = (int)$valor;
            $this->recalcularValores();
            $this->salvarHistorico();
            
            return [
                'perdas' => $this->perdas[$localizacao],
                'total_linha' => array_sum($this->perdas[$localizacao]),
                'localizacao' => $this->localizacoes[$localizacao],
                'porcentagens' => $this->calcularPorcentagens($localizacao),
                'totais_perdas' => $this->getTotaisPerdas(),
                'totais' => $this->getTotais(),
                'mensagem' => 'Dados salvos com sucesso!'
            ];
        }
        return false;
    }
}

// Tratamento de requisição AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estoque = new EstoqueManager();
    $resultado = null;
    
    if ($_POST['action'] === 'update') {
        $resultado = $estoque->atualizarPosicao(
            $_POST['localizacao'],
            $_POST['campo'],
            $_POST['valor']
        );
    } elseif ($_POST['action'] === 'update_perda') {
        $resultado = $estoque->atualizarPerda(
            $_POST['localizacao'],
            $_POST['campo'],
            $_POST['valor']
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode($resultado);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
    <style>
        .editable, .editable-perda {
            background-color: #f8f9fa;
            cursor: pointer;
            padding: 5px;
        }
        .editable:hover, .editable-perda:hover {
            background-color: #e9ecef;
        }
        .editing {
            padding: 0;
        }
        .editing input {
            width: 100%;
            height: 100%;
            padding: 5px;
            border: 2px solid #0d6efd;
            border-radius: 4px;
        }
        .table-dark th {
            background-color: #212529;
        }
        .percentage-cell {
            font-weight: bold;
        }
        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>

<!-- Continua na próxima parte... -->
<body>
    <?php
    $estoque = new EstoqueManager();
    $dados = $estoque->getDados();
    $totais = $estoque->getTotais();
    $perdas = $estoque->getPerdas();
    $totaisPerdas = $estoque->getTotaisPerdas();
    ?>

    <div class="container mt-4 mb-5">
        <h2 class="mb-4">Gestão de Estoque</h2>
        <div id="toast-container"></div>

        <!-- Primeira Tabela -->
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>LOCALIZAÇÃO</th>
                        <th>% POSIÇÃO OCUPADA</th>
                        <th>% POSIÇÃO VAZIA</th>
                        <th>% DE PERDA / RESERVA</th>
                        <th>POSIÇÕES OCUPADAS</th>
                        <th>VAZIAS</th>
                        <th>PERDAS / RESERVA</th>
                        <th>POSIÇÕES TOTAIS</th>
                        <th>VAZIAS REAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $loc => $valores): 
                        $porcentagens = $estoque->calcularPorcentagens($loc);
                    ?>
                    <tr>
                        <td><?php echo $loc; ?></td>
                        <td class="percentage-cell" data-percentage="ocupada-<?php echo $loc; ?>"><?php echo $porcentagens['ocupada']; ?>%</td>
                        <td class="percentage-cell" data-percentage="vazia-<?php echo $loc; ?>"><?php echo $porcentagens['vazia']; ?>%</td>
                        <td class="percentage-cell" data-percentage="perda-<?php echo $loc; ?>"><?php echo $porcentagens['perda']; ?>%</td>
                        <td data-value="ocupadas-<?php echo $loc; ?>"><?php echo $valores['ocupadas']; ?></td>
                        <td data-value="vazias-<?php echo $loc; ?>"><?php echo $valores['vazias']; ?></td>
                        <td data-value="perdas-<?php echo $loc; ?>"><?php echo $valores['perdas']; ?></td>
                        <td class="editable" data-loc="<?php echo $loc; ?>" data-field="total"><?php echo $valores['total']; ?></td>
                        <td class="editable" data-loc="<?php echo $loc; ?>" data-field="vazias_real"><?php echo $valores['vazias_real']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-secondary fw-bold">
                        <td>TOTAL</td>
                        <td colspan="3"></td>
                        <td id="total-ocupadas"><?php echo $totais['ocupadas']; ?></td>
                        <td id="total-vazias"><?php echo $totais['vazias']; ?></td>
                        <td id="total-perdas"><?php echo $totais['perdas']; ?></td>
                        <td id="total-total"><?php echo $totais['total']; ?></td>
                        <td id="total-vazias-real"><?php echo $totais['vazias_real']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Segunda Tabela -->
        <h4 class="mb-3">Totais de Perdas de Endereços</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>LOCALIZAÇÃO</th>
                        <th>HIDRANTE/VIGA</th>
                        <th>CORREDOR</th>
                        <th>RESERVADO/PED</th>
                        <th>RESERV/ALM</th>
                        <th>AMASSADO/INEXISTENTE</th>
                        <th>TOTAIS POR ZONEAMENTO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perdas as $loc => $valores): ?>
                    <tr>
                        <td><?php echo $loc; ?></td>
                        <td class="editable-perda" data-loc="<?php echo $loc; ?>" data-field="hidrante"><?php echo $valores['hidrante']; ?></td>
                        <td class="editable-perda" data-loc="<?php echo $loc; ?>" data-field="corredor"><?php echo $valores['corredor']; ?></td>
                        <td class="editable-perda" data-loc="<?php echo $loc; ?>" data-field="reservado"><?php echo $valores['reservado']; ?></td>
                        <td class="editable-perda" data-loc="<?php echo $loc; ?>" data-field="almoxarifado"><?php echo $valores['almoxarifado']; ?></td>
                        <td class="editable-perda" data-loc="<?php echo $loc; ?>" data-field="amassado"><?php echo $valores['amassado']; ?></td>
                        <td class="fw-bold" data-total-linha="<?php echo $loc; ?>"><?php echo array_sum($valores); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-secondary fw-bold">
                        <td>TOTAL</td>
                        <td id="total-hidrante"><?php echo $totaisPerdas['hidrante']; ?></td>
                        <td id="total-corredor"><?php echo $totaisPerdas['corredor']; ?></td>
                        <td id="total-reservado"><?php echo $totaisPerdas['reservado']; ?></td>
                        <td id="total-almoxarifado"><?php echo $totaisPerdas['almoxarifado']; ?></td>
                        <td id="total-amassado"><?php echo $totaisPerdas['amassado']; ?></td>
                        <td id="total-perdas-geral"><?php echo $totaisPerdas['total']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Configuração para células editáveis
            const configureEditableCells = (selector, updateFunction) => {
                document.querySelectorAll(selector).forEach(cell => {
                    cell.addEventListener('click', function() {
                        if (this.classList.contains('editing')) return;
                        
                        const value = this.textContent;
                        const input = document.createElement('input');
                        input.type = 'number';
                        input.value = value;
                        input.min = '0';
                        
                        this.textContent = '';
                        this.appendChild(input);
                        this.classList.add('editing');
                        input.focus();
                        
                        const handleUpdate = () => {
                            if (input.value !== value) {
                                updateFunction(cell, input.value);
                            } else {
                                cell.classList.remove('editing');
                                cell.textContent = value;
                            }
                        };
                        
                        input.addEventListener('blur', handleUpdate);
                        input.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                handleUpdate();
                            }
                        });
                    });
                });
            };

            // Configurar células editáveis para ambas as tabelas
            configureEditableCells('.editable', updateValue);
            configureEditableCells('.editable-perda', updatePerda);

            function updateValue(cell, newValue) {
                const loc = cell.dataset.loc;
                const field = cell.dataset.field;
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update&localizacao=${loc}&campo=${field}&valor=${newValue}`
                })
                .then(response => response.json())
                .then(data => {
                    cell.classList.remove('editing');
                    cell.textContent = newValue;
                    
                    // Atualiza valores calculados
                    const valores = data.valores;
                    document.querySelector(`[data-value="ocupadas-${loc}"]`).textContent = valores.ocupadas;
                    document.querySelector(`[data-value="vazias-${loc}"]`).textContent = valores.vazias;
                    document.querySelector(`[data-value="perdas-${loc}"]`).textContent = valores.perdas;
                    
                    // Atualiza porcentagens
                    const porcentagens = data.porcentagens;
                    document.querySelector(`[data-percentage="ocupada-${loc}"]`).textContent = porcentagens.ocupada + '%';
                    document.querySelector(`[data-percentage="vazia-${loc}"]`).textContent = porcentagens.vazia + '%';
                    document.querySelector(`[data-percentage="perda-${loc}"]`).textContent = porcentagens.perda + '%';
                    
                    // Atualiza totais
                    const totais = data.totais;
                    document.getElementById('total-ocupadas').textContent = totais.ocupadas;
                    document.getElementById('total-vazias').textContent = totais.vazias;
                    document.getElementById('total-perdas').textContent = totais.perdas;
                    document.getElementById('total-total').textContent = totais.total;
                    document.getElementById('total-vazias-real').textContent = totais.vazias_real;

                    if (data.mensagem) {
                        toastr.success(data.mensagem);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    cell.classList.remove('editing');
                    cell.textContent = cell.dataset.originalValue || '0';
                    toastr.error('Erro ao salvar os dados');
                });
            }

            function updatePerda(cell, newValue) {
                const loc = cell.dataset.loc;
                const field = cell.dataset.field;
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_perda&localizacao=${loc}&campo=${field}&valor=${newValue}`
                })
                .then(response => response.json())
                .then(data => {
                    cell.classList.remove('editing');
                    cell.textContent = newValue;
                    
                    // Atualiza total da linha
                    document.querySelector(`[data-total-linha="${loc}"]`).textContent = data.total_linha;
                    
                    // Atualiza totais das colunas de perdas
                    const totaisPerdas = data.totais_perdas;
                    document.getElementById('total-hidrante').textContent = totaisPerdas.hidrante;
                    document.getElementById('total-corredor').textContent = totaisPerdas.corredor;
                    document.getElementById('total-reservado').textContent = totaisPerdas.reservado;
                    document.getElementById('total-almoxarifado').textContent = totaisPerdas.almoxarifado;
                    document.getElementById('total-amassado').textContent = totaisPerdas.amassado;
                    document.getElementById('total-perdas-geral').textContent = totaisPerdas.total;
                    
                    // Atualiza valores na primeira tabela
                    document.querySelector(`[data-value="perdas-${loc}"]`).textContent = data.total_linha;
                    
                    const porcentagens = data.porcentagens;
                    document.querySelector(`[data-percentage="ocupada-${loc}"]`).textContent = porcentagens.ocupada + '%';
                    document.querySelector(`[data-percentage="vazia-${loc}"]`).textContent = porcentagens.vazia + '%';
                    document.querySelector(`[data-percentage="perda-${loc}"]`).textContent = porcentagens.perda + '%';
                    
                    const totais = data.totais;
                    document.getElementById('total-ocupadas').textContent = totais.ocupadas;
                    document.getElementById('total-vazias').textContent = totais.vazias;
                    document.getElementById('total-perdas').textContent = totais.perdas;
                    document.getElementById('total-total').textContent = totais.total;
                    document.getElementById('total-vazias-real').textContent = totais.vazias_real;

                    if (data.mensagem) {
                        toastr.success(data.mensagem);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    cell.classList.remove('editing');
                    cell.textContent = cell.dataset.originalValue || '0';
                    toastr.error('Erro ao salvar os dados');
                });
            }
        });
    </script>
</body>
</html>