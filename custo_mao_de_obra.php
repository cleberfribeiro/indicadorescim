<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

class GestaoMDO {
    private $arquivoFaturas = 'dados/faturas_mdo.txt';
    private $arquivoServicos = 'dados/servicos_mdo.txt';

    public function __construct() {
        if (!file_exists('dados')) {
            mkdir('dados', 0777, true);
        }
        
        // Cria arquivos se não existirem
        foreach ([$this->arquivoFaturas, $this->arquivoServicos] as $arquivo) {
            if (!file_exists($arquivo)) {
                file_put_contents($arquivo, '');
            }
        }
    }

    public function salvarDados($dados) {
        // Salvar fatura
        $linhaFatura = implode('|', [
            date('Y-m-d H:i:s'),
            $dados['numero_fatura'],
            $dados['data_emissao'],
            $dados['data_vencimento'],
            $dados['valor_total']
        ]) . "\n";

        file_put_contents($this->arquivoFaturas, $linhaFatura, FILE_APPEND);

        // Salvar serviços
        foreach ($dados['servicos'] as $servico) {
            $linhaServico = implode('|', [
                $dados['numero_fatura'],
                $servico['data'],
                $servico['processo'],
                $servico['servico'],
                $servico['operacao'],
                $servico['valor']
            ]) . "\n";
            
            file_put_contents($this->arquivoServicos, $linhaServico, FILE_APPEND);
        }

        return true;
    }

    public function getResumo() {
        $servicos = file($this->arquivoServicos, FILE_IGNORE_NEW_LINES);
        $resumo = [
            'DIARIA' => 0,
            'HORA_EXTRA' => 0,
            'DESCONTO' => 0,
            'DESCARGA' => 0
        ];

        foreach ($servicos as $servico) {
            $dados = explode('|', $servico);
            if (count($dados) >= 6) {
                $valor = $this->converterParaFloat($dados[5]);
                $tipo = $this->classificarServico($dados[3]);
                $resumo[$tipo] += $valor;
            }
        }

        return $resumo;
    }

    private function classificarServico($descricao) {
        $descricao = strtoupper($descricao);
        if (strpos($descricao, 'DIARIA') !== false) return 'DIARIA';
        if (strpos($descricao, 'HORA EXTRA') !== false) return 'HORA_EXTRA';
        if (strpos($descricao, 'DESCONTO') !== false) return 'DESCONTO';
        if (strpos($descricao, 'DESCARGA') !== false) return 'DESCARGA';
        return 'OUTROS';
    }

    private function converterParaFloat($valor) {
        return floatval(str_replace(['R$', '.', ','], ['', '', '.'], $valor));
    }

    public function getServicos($filtros = []) {
        $servicos = file($this->arquivoServicos, FILE_IGNORE_NEW_LINES);
        $resultado = [];

        foreach ($servicos as $servico) {
            $dados = explode('|', $servico);
            if (count($dados) >= 6) {
                if (!empty($filtros['data_inicio']) && strtotime($dados[1]) < strtotime($filtros['data_inicio'])) {
                    continue;
                }
                if (!empty($filtros['data_fim']) && strtotime($dados[1]) > strtotime($filtros['data_fim'])) {
                    continue;
                }
                if (!empty($filtros['tipo']) && $this->classificarServico($dados[3]) !== $filtros['tipo']) {
                    continue;
                }

                $resultado[] = [
                    'fatura' => $dados[0],
                    'data' => $dados[1],
                    'processo' => $dados[2],
                    'servico' => $dados[3],
                    'operacao' => $dados[4],
                    'valor' => $dados[5]
                ];
            }
        }

        return $resultado;
    }
}

// Processar entrada manual de dados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    $gestao = new GestaoMDO();

    try {
        if ($_POST['action'] === 'salvar') {
            $dados = [
                'numero_fatura' => $_POST['numero_fatura'],
                'data_emissao' => $_POST['data_emissao'],
                'data_vencimento' => $_POST['data_vencimento'],
                'valor_total' => $_POST['valor_total'],
                'servicos' => json_decode($_POST['servicos'], true)
            ];

            if ($gestao->salvarDados($dados)) {
                $response = [
                    'success' => true,
                    'message' => 'Dados salvos com sucesso!',
                    'resumo' => $gestao->getResumo()
                ];
            }
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Erro ao processar dados: ' . $e->getMessage()
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Mão de Obra Terceiros - CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #34495e;
            --secondary-color: #2c3e50;
            --accent-color: #3498db;
        }

        body {
            background-color: #f5f7fa;
        }

        .container {
            max-width: 1400px;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
        }

        .form-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .resumo-card {
            text-align: center;
            padding: 20px;
            transition: transform 0.3s ease;
        }

        .resumo-card:hover {
            transform: translateY(-5px);
        }

        .valor {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }

        .loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .btn-add-servico {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-add-servico:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .servico-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
    </style>
</head>

<!-- Continua... -->
<body>
    <!-- Loading Overlay -->
    <div class="loading">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>

    <div class="container">
        <h2 class="mb-4">Gestão de Mão de Obra Terceiros</h2>

        <!-- Formulário de Entrada -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Entrada de Dados da Fatura</h5>
            </div>
            <div class="card-body">
                <form id="faturaForm">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Número da Fatura</label>
                                <input type="text" class="form-control" name="numero_fatura" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Data Emissão</label>
                                <input type="date" class="form-control" name="data_emissao" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Data Vencimento</label>
                                <input type="date" class="form-control" name="data_vencimento" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Valor Total</label>
                                <input type="text" class="form-control" name="valor_total" required>
                            </div>
                        </div>
                    </div>

                    <div class="servicos-container">
                        <h5 class="mb-3">Serviços</h5>
                        <div id="servicosLista">
                            <!-- Serviços serão adicionados aqui -->
                        </div>
                        <button type="button" class="btn btn-add-servico" onclick="adicionarServico()">
                            <i class="fas fa-plus me-2"></i>Adicionar Serviço
                        </button>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Salvar Fatura
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="row mb-4">
            <?php
            $gestao = new GestaoMDO();
            $resumo = $gestao->getResumo();
            
            $cards = [
                ['tipo' => 'DIARIA', 'titulo' => 'DIÁRIA AJUDANTE', 'icone' => 'user'],
                ['tipo' => 'HORA_EXTRA', 'titulo' => 'HORA EXTRA', 'icone' => 'clock'],
                ['tipo' => 'DESCONTO', 'titulo' => 'DESCONTO', 'icone' => 'percentage'],
                ['tipo' => 'DESCARGA', 'titulo' => 'DESCARGA CNTR', 'icone' => 'truck']
            ];

            foreach ($cards as $card):
            ?>
            <div class="col-md-3">
                <div class="card resumo-card">
                    <i class="fas fa-<?php echo $card['icone']; ?> fa-2x text-primary"></i>
                    <h5 class="mt-3"><?php echo $card['titulo']; ?></h5>
                    <div class="valor" id="valor-<?php echo strtolower($card['tipo']); ?>">
                        R$ <?php echo number_format($resumo[$card['tipo']], 2, ',', '.'); ?>
                    </div>
                    <small class="text-muted">Total acumulado</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtros e Grid -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Histórico de Serviços</h5>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Data Início</label>
                            <input type="date" class="form-control" id="filtroDataInicio">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Data Fim</label>
                            <input type="date" class="form-control" id="filtroDataFim">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tipo Serviço</label>
                            <select class="form-control" id="filtroTipoServico">
                                <option value="">Todos</option>
                                <option value="DIARIA">Diária</option>
                                <option value="HORA_EXTRA">Hora Extra</option>
                                <option value="DESCARGA">Descarga</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary mt-4" onclick="aplicarFiltros()">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Processo</th>
                                <th>Serviço</th>
                                <th>Operação</th>
                                <th>Valor</th>
                                <th>Fatura</th>
                            </tr>
                        </thead>
                        <tbody id="servicosGrid">
                            <?php
                            $servicos = $gestao->getServicos();
                            foreach ($servicos as $servico):
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($servico['data'])); ?></td>
                                <td><?php echo $servico['processo']; ?></td>
                                <td><?php echo $servico['servico']; ?></td>
                                <td><?php echo $servico['operacao']; ?></td>
                                <td><?php echo 'R$ ' . number_format($servico['valor'], 2, ',', '.'); ?></td>
                                <td><?php echo $servico['fatura']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        let servicosCount = 0;

        // Template para novo serviço
        function getServicoTemplate() {
            servicosCount++;
            return `
                <div class="servico-item" id="servico-${servicosCount}">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Data</label>
                                <input type="date" class="form-control" name="servico_data_${servicosCount}" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Processo</label>
                                <input type="text" class="form-control" name="servico_processo_${servicosCount}" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Serviço</label>
                                <select class="form-control" name="servico_tipo_${servicosCount}" required>
                                    <option value="DIARIA AJUDANTE">Diária Ajudante</option>
                                    <option value="HORA EXTRA AJUD">Hora Extra</option>
                                    <option value="DESCARGA CNTR">Descarga Container</option>
                                    <option value="DESCONTO">Desconto</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Valor</label>
                                <input type="text" class="form-control money" name="servico_valor_${servicosCount}" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Operação</label>
                                <input type="text" class="form-control" name="servico_operacao_${servicosCount}" value="MDO" required>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger mt-4" onclick="removerServico(${servicosCount})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // Adicionar novo serviço
        function adicionarServico() {
            document.getElementById('servicosLista').insertAdjacentHTML('beforeend', getServicoTemplate());
            // Inicializar máscara monetária para o novo campo
            initMoneyMask();
        }

        // Remover serviço
        function removerServico(id) {
            document.getElementById(`servico-${id}`).remove();
        }

        // Máscara para valores monetários
        function initMoneyMask() {
            document.querySelectorAll('.money').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = (value/100).toFixed(2);
                    e.target.value = value;
                });
            });
        }

        // Processar formulário
        document.getElementById('faturaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const servicos = [];
            
            // Coletar dados dos serviços
            for (let i = 1; i <= servicosCount; i++) {
                const servicoEl = document.getElementById(`servico-${i}`);
                if (servicoEl) {
                    servicos.push({
                        data: formData.get(`servico_data_${i}`),
                        processo: formData.get(`servico_processo_${i}`),
                        servico: formData.get(`servico_tipo_${i}`),
                        operacao: formData.get(`servico_operacao_${i}`),
                        valor: formData.get(`servico_valor_${i}`)
                    });
                }
            }

            const dados = {
                action: 'salvar',
                numero_fatura: formData.get('numero_fatura'),
                data_emissao: formData.get('data_emissao'),
                data_vencimento: formData.get('data_vencimento'),
                valor_total: formData.get('valor_total'),
                servicos: JSON.stringify(servicos)
            };

            // Mostrar loading
            document.querySelector('.loading').style.display = 'flex';

            // Enviar dados
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(dados)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Dados salvos com sucesso!');
                    window.location.reload();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                alert('Erro ao salvar dados: ' + error.message);
            })
            .finally(() => {
                document.querySelector('.loading').style.display = 'none';
            });
        });

        // Aplicar filtros
        function aplicarFiltros() {
            const filtros = {
                data_inicio: document.getElementById('filtroDataInicio').value,
                data_fim: document.getElementById('filtroDataFim').value,
                tipo: document.getElementById('filtroTipoServico').value
            };

            document.querySelector('.loading').style.display = 'flex';

            fetch('?action=filtrar&' + new URLSearchParams(filtros))
                .then(response => response.json())
                .then(data => {
                    atualizarGrid(data);
                })
                .catch(error => {
                    alert('Erro ao filtrar dados');
                })
                .finally(() => {
                    document.querySelector('.loading').style.display = 'none';
                });
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            initMoneyMask();
            adicionarServico(); // Adiciona primeiro serviço automaticamente
        });
    </script>

</body>
</html>
