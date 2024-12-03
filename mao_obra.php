<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();
if (!isset($_SESSION['usuario'])) {
   header("Location: index.php");
   exit();
}

class GestaoMDO {
   private $arquivoFaturas = 'DADOS/faturas_mdo.txt';
   private $arquivoServicos = 'DADOS/servicos_mdo.txt';

   public function __construct() {
       if (!file_exists('DADOS')) {
           mkdir('DADOS', 0777, true);
       }
       foreach ([$this->arquivoFaturas, $this->arquivoServicos] as $arquivo) {
           if (!file_exists($arquivo)) {
               file_put_contents($arquivo, '');
           }
       }
   }

   public function processarCSV($arquivo) {
       try {
           $conteudo = file_get_contents($arquivo);
           $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'UTF-8,ISO-8859-1');
           $linhas = explode("\n", $conteudo);
           
           error_log("Processando arquivo - número de linhas: " . count($linhas));

           $fatura = [
               'numero_fatura' => '',
               'data_emissao' => '',
               'data_vencimento' => '',
               'valor_total' => 0,
               'servicos' => []
           ];

           $encontrouCabecalho = false;
           foreach ($linhas as $i => $linha) {
               $linha = trim($linha);

               if (strpos($linha, 'FATURA Nº') !== false && 
                   strpos($linha, 'DATA EMISSÃO') !== false && 
                   strpos($linha, 'DATA VENCIMENTO') !== false) {
                   $encontrouCabecalho = true;
                   continue;
               }

               if ($encontrouCabecalho && 
                   preg_match('/^\s*(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+(?:[,.]\d{2})?)\s*/', $linha, $matches)) {
                   $fatura['numero_fatura'] = trim($matches[1]);
                   $fatura['data_emissao'] = trim($matches[2]);
                   $fatura['data_vencimento'] = trim($matches[3]);
                   $fatura['valor_total'] = $this->converterValor($matches[4]);
                   break;
               }
           }

           $servicosEncontrados = [];
           $capturandoServico = false;
           $pedidoAtual = '';

           foreach ($linhas as $linha) {
               $linha = trim($linha);
               if (empty($linha)) continue;

               if (preg_match('/^Nº\s+Pedido:\s+(\d+)/', $linha, $matches)) {
                   $pedidoAtual = $matches[1];
                   continue;
               }

               if (strpos($linha, 'Nº RPS') !== false && 
                   strpos($linha, 'Data Serviço') !== false) {
                   $capturandoServico = true;
                   continue;
               }

               if ($capturandoServico && 
                   preg_match('/^(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+.*?(DIÁRIA\s+AJUDANTE|HORA\s+EXTRA|DESCONTO|DESCARGA).*?\s+(\d+(?:[,.]\d{2})?)\s+(\d+(?:[,.]\d{2})?)\s+(\d+(?:[,.]\d{2})?)\s*$/', $linha, $matches)) {
                   
                   $chaveUnica = $matches[2] . '-' . $matches[6];
                   
                   if (!isset($servicosEncontrados[$chaveUnica])) {
                       $servicosEncontrados[$chaveUnica] = true;
                       
                       $servico = [
                           'processo' => $matches[1],
                           'data' => $matches[2],
                           'servico' => trim($matches[3]),
                           'operacao' => 'MDO',
                           'quantidade' => $this->converterValor($matches[4]),
                           'valor_unitario' => $this->converterValor($matches[5]),
                           'valor' => $this->converterValor($matches[6])
                       ];
                       $fatura['servicos'][] = $servico;
                   }
               }
           }

           if (empty($fatura['numero_fatura'])) {
               throw new Exception('Não foi possível encontrar as informações da fatura no arquivo.');
           }

           if (empty($fatura['servicos'])) {
               throw new Exception('Não foram encontrados serviços no arquivo.');
           }

           $somaServicos = array_sum(array_column($fatura['servicos'], 'valor'));
           if (abs($somaServicos - $fatura['valor_total']) > 1) {
               error_log("Aviso: Soma dos serviços ($somaServicos) diferente do valor total da fatura ({$fatura['valor_total']})");
           }

           $this->limparRegistrosAnteriores($fatura['numero_fatura']);
           $this->salvarDados($fatura);
           
           return ['success' => true, 'message' => 'Fatura processada com sucesso'];

       } catch (Exception $e) {
           error_log("Erro no processamento do arquivo: " . $e->getMessage());
           return ['success' => false, 'message' => 'Erro ao processar arquivo: ' . $e->getMessage()];
       }
   }

   private function converterValor($valor) {
       // Remove caracteres não numéricos exceto vírgula e ponto
       $valor = preg_replace('/[^0-9,.]/', '', $valor);
       
       // Substitui vírgula por ponto
       $valor = str_replace(',', '.', $valor);
       
       // Remove pontos de milhar e mantém o ponto decimal
       if (substr_count($valor, '.') > 1) {
           $partes = explode('.', $valor);
           $decimal = array_pop($partes);
           $inteiro = join('', $partes);
           $valor = $inteiro . '.' . $decimal;
       }
       
       // Converte para float
       $valorFloat = floatval($valor);
       
       // Ajusta valores menores que 100 multiplicando por 1000
       // Isso converterá 3,10 para 3.100,00
       if ($valorFloat > 0 && $valorFloat <= 100) {
           return $valorFloat * 1000;
       }
       
       return $valorFloat;
   }

   private function formatarData($data) {
       if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $matches)) {
           return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
       }
       return $data;
   }

   private function limparRegistrosAnteriores($numeroFatura) {
       $faturas = array_filter(
           file($this->arquivoFaturas, FILE_IGNORE_NEW_LINES),
           function($linha) use ($numeroFatura) {
               $dados = explode('|', $linha);
               return $dados[1] !== $numeroFatura;
           }
       );
       
       $servicos = array_filter(
           file($this->arquivoServicos, FILE_IGNORE_NEW_LINES),
           function($linha) use ($numeroFatura) {
               $dados = explode('|', $linha);
               return $dados[0] !== $numeroFatura;
           }
       );

       file_put_contents($this->arquivoFaturas, implode("\n", $faturas) . (empty($faturas) ? '' : "\n"));
       file_put_contents($this->arquivoServicos, implode("\n", $servicos) . (empty($servicos) ? '' : "\n"));
   }

   private function salvarDados($fatura) {
       $linhaFatura = implode('|', [
           date('Y-m-d H:i:s'),
           $fatura['numero_fatura'],
           $this->formatarData($fatura['data_emissao']),
           $this->formatarData($fatura['data_vencimento']),
           $fatura['valor_total']
       ]) . "\n";

       file_put_contents($this->arquivoFaturas, $linhaFatura, FILE_APPEND);

       foreach ($fatura['servicos'] as $servico) {
           $linhaServico = implode('|', [
               $fatura['numero_fatura'],
               $this->formatarData($servico['data']),
               $servico['processo'],
               $servico['servico'],
               $servico['operacao'],
               $servico['valor']
           ]) . "\n";
           file_put_contents($this->arquivoServicos, $linhaServico, FILE_APPEND);
       }
   }

   public function excluirFatura($numeroFatura) {
       try {
           $this->limparRegistrosAnteriores($numeroFatura);
           return ['success' => true, 'message' => 'Fatura excluída com sucesso'];
       } catch (Exception $e) {
           return ['success' => false, 'message' => 'Erro ao excluir fatura: ' . $e->getMessage()];
       }
   }

   public function getResumo() {
       $servicos = file($this->arquivoServicos, FILE_IGNORE_NEW_LINES);
       $resumo = [
           'diaria' => 0,
           'hora_extra' => 0,
           'desconto' => 0,
           'descarga' => 0,
           'total' => 0
       ];

       foreach ($servicos as $servico) {
           $dados = explode('|', $servico);
           if (count($dados) >= 6) {
               $valor = floatval($dados[5]);
               $tipo = strtoupper($dados[3]);
               
               if (strpos($tipo, 'DIÁRIA') !== false || strpos($tipo, 'DIARIA') !== false) {
                   $resumo['diaria'] += $valor;
               } elseif (strpos($tipo, 'HORA EXTRA') !== false) {
                   $resumo['hora_extra'] += $valor;
               } elseif (strpos($tipo, 'DESCONTO') !== false) {
                   $resumo['desconto'] += $valor;
               } elseif (strpos($tipo, 'DESCARGA') !== false) {
                   $resumo['descarga'] += $valor;
               }
               $resumo['total'] += $valor;
           }
       }
       return $resumo;
   }

   public function getFaturas() {
       $faturas = file($this->arquivoFaturas, FILE_IGNORE_NEW_LINES);
       $lista = [];
       foreach ($faturas as $fatura) {
           $dados = explode('|', $fatura);
           if (count($dados) >= 5) {
               $lista[] = [
                   'data_registro' => $dados[0],
                   'numero_fatura' => $dados[1],
                   'data_emissao' => $dados[2],
                   'data_vencimento' => $dados[3],
                   'valor_total' => floatval($dados[4])
               ];
           }
       }
       return array_reverse($lista);
   }

   public function getServicosFatura($numeroFatura) {
       $servicos = file($this->arquivoServicos, FILE_IGNORE_NEW_LINES);
       $lista = [];
       foreach ($servicos as $servico) {
           $dados = explode('|', $servico);
           if ($dados[0] === $numeroFatura) {
               $lista[] = [
                   'data' => $dados[1],
                   'processo' => $dados[2],
                   'servico' => $dados[3],
                   'operacao' => $dados[4],
                   'valor' => floatval($dados[5])
               ];
           }
       }
       return $lista;
   }
}

// Tratamento das requisições AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
   header('Content-Type: application/json');
   $gestao = new GestaoMDO();
   echo json_encode($gestao->processarCSV($_FILES['csv']['tmp_name']));
   exit;
}

if (isset($_GET['action'])) {
   header('Content-Type: application/json');
   $gestao = new GestaoMDO();
   
   switch ($_GET['action']) {
       case 'excluir':
           if (isset($_GET['fatura'])) {
               echo json_encode($gestao->excluirFatura($_GET['fatura']));
           }
           break;
       case 'resumo':
           echo json_encode($gestao->getResumo());
           break;
       case 'faturas':
           echo json_encode($gestao->getFaturas());
           break;
       case 'servicos':
           if (isset($_GET['fatura'])) {
               echo json_encode($gestao->getServicosFatura($_GET['fatura']));
           }
           break;
   }
   exit;
}

// Limpa o buffer antes do HTML
ob_clean();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Mão de Obra Terceiros - CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #27ae60;
            --info: #3498db;
            --warning: #f39c12;
            --danger: #e74c3c;
        }

        body { 
            background-color: #f5f7fa; 
            padding-bottom: 2rem;
        }
        
        .container { 
            max-width: 1400px; 
            padding: 20px; 
        }

        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: white;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .upload-area:hover {
            border-color: var(--info);
            background: #f8f9fa;
        }

        .upload-area.drag-over {
            border-color: var(--success);
            background: #f0fff4;
        }

        .resumo-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            margin-bottom: 1rem;
        }

        .resumo-card:hover {
            transform: translateY(-5px);
        }

        .loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .fatura-row { cursor: pointer; }
        .fatura-row:hover { background-color: #f8f9fa; }

        .servicos-collapse {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 0 0 10px 10px;
            margin-top: -1px;
        }

        .btn-outline-info:hover {
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div id="modalConfirmacao" class="modal">
        <div class="modal-content">
            <h4>Confirmar Exclusão</h4>
            <p>Tem certeza que deseja excluir esta fatura?</p>
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button class="btn btn-danger" onclick="excluirFatura()">Excluir</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Gestão de Mão de Obra Terceiros</h2>
            <button class="btn btn-outline-info" onclick="atualizarDashboard()">
                <i class="fas fa-sync-alt me-2"></i>
                Atualizar Dados
            </button>
        </div>

        <div class="upload-area" id="uploadArea">
            <i class="fas fa-file-upload fa-3x text-success mb-3"></i>
            <h4>Arraste seu arquivo aqui ou clique para selecionar</h4>
            <p class="text-muted">Formatos aceitos: CSV, TXT</p>
            <input type="file" id="csvFile" accept=".csv,.txt" style="display: none">
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="resumo-card">
                    <i class="fas fa-user-friends fa-2x text-primary mb-3"></i>
                    <h5>Diária Ajudante</h5>
                    <h3 class="text-primary" id="valorDiaria">R$ 0,00</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-card">
                    <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                    <h5>Hora Extra</h5>
                    <h3 class="text-warning" id="valorHoraExtra">R$ 0,00</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-card">
                    <i class="fas fa-minus-circle fa-2x text-danger mb-3"></i>
                    <h5>Descontos</h5>
                    <h3 class="text-danger" id="valorDesconto">R$ 0,00</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-card">
                    <i class="fas fa-truck-loading fa-2x text-success mb-3"></i>
                    <h5>Descarga</h5>
                    <h3 class="text-success" id="valorDescarga">R$ 0,00</h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Faturas Importadas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nº Fatura</th>
                                <th>Data Emissão</th>
                                <th>Vencimento</th>
                                <th>Valor Total</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="faturasTabela"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        let faturaParaExcluir = null;

        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('csvFile');

            uploadArea.addEventListener('click', () => fileInput.click());

            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('drag-over');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('drag-over');
                }, false);
            });

            uploadArea.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (files.length) {
                    processarArquivo(files[0]);
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length) {
                    processarArquivo(e.target.files[0]);
                }
            });

            function processarArquivo(file) {
                const formData = new FormData();
                formData.append('csv', file);
                showLoading();

                fetch('mao_obra.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        atualizarDashboard();
                        alert(data.message);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao processar arquivo: ' + error.message);
                })
                .finally(() => {
                    hideLoading();
                    fileInput.value = '';
                });
            }

            window.atualizarDashboard = function() {
                showLoading();
                fetch('mao_obra.php?action=resumo')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('valorDiaria').textContent = formatarMoeda(data.diaria);
                    document.getElementById('valorHoraExtra').textContent = formatarMoeda(data.hora_extra);
                    document.getElementById('valorDesconto').textContent = formatarMoeda(data.desconto);
                    document.getElementById('valorDescarga').textContent = formatarMoeda(data.descarga);
                    carregarFaturas();
                })
                .catch(error => {
                    console.error('Erro ao atualizar dashboard:', error);
                    alert('Erro ao atualizar informações do dashboard');
                })
                .finally(() => {
                    hideLoading();
                });
            }

            function carregarFaturas() {
                fetch('mao_obra.php?action=faturas')
                .then(response => response.json())
                .then(faturas => {
                    const tbody = document.getElementById('faturasTabela');
                    tbody.innerHTML = '';
                    
                    if (faturas.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Nenhuma fatura importada
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    faturas.forEach(fatura => {
                        const tr = document.createElement('tr');
                        tr.className = 'fatura-row';
                        tr.innerHTML = `
                            <td>${fatura.numero_fatura}</td>
                            <td>${formatarData(fatura.data_emissao)}</td>
                            <td>${formatarData(fatura.data_vencimento)}</td>
                            <td>${formatarMoeda(fatura.valor_total)}</td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-info text-white" onclick="toggleServicos('${fatura.numero_fatura}', this)">
                                        <i class="fas fa-chevron-down"></i>
                                        Detalhes
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="confirmarExclusao('${fatura.numero_fatura}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar faturas:', error);
                    alert('Erro ao carregar lista de faturas');
                });
            }

            window.toggleServicos = function(numeroFatura, button) {
                const row = button.closest('tr');
                let servicosRow = row.nextElementSibling;
                
                if (servicosRow && servicosRow.classList.contains('servicos-row')) {
                    servicosRow.remove();
                    button.querySelector('i').classList.replace('fa-chevron-up', 'fa-chevron-down');
                } else {
                    showLoading();
                    fetch(`mao_obra.php?action=servicos&fatura=${numeroFatura}`)
                    .then(response => response.json())
                    .then(servicos => {
                        const newRow = document.createElement('tr');
                        newRow.className = 'servicos-row';
                        
                        let html = `
                            <td colspan="5">
                                <div class="servicos-collapse">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Processo</th>
                                                <th>Serviço</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;
                        
                        let totalServicos = 0;
                        servicos.forEach(s => {
                            totalServicos += parseFloat(s.valor);
                            html += `
                                <tr>
                                    <td>${formatarData(s.data)}</td>
                                    <td>${s.processo}</td>
                                    <td>${s.servico}</td>
                                    <td>${formatarMoeda(s.valor)}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                <tr class="table-info">
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong>${formatarMoeda(totalServicos)}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            `;
                        
                        newRow.innerHTML = html;
                        row.after(newRow);
                        button.querySelector('i').classList.replace('fa-chevron-down', 'fa-chevron-up');
                    })
                    .catch(error => {
                        console.error('Erro ao carregar serviços:', error);
                        alert('Erro ao carregar detalhes dos serviços');
                    })
                    .finally(() => {
                        hideLoading();
                    });
                }
            };

            window.confirmarExclusao = function(numeroFatura) {
                faturaParaExcluir = numeroFatura;
                document.getElementById('modalConfirmacao').style.display = 'block';
            }

            window.fecharModal = function() {
                document.getElementById('modalConfirmacao').style.display = 'none';
                faturaParaExcluir = null;
            }

            window.excluirFatura = function() {
                if (!faturaParaExcluir) return;
                
                showLoading();
                fetch(`mao_obra.php?action=excluir&fatura=${faturaParaExcluir}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            atualizarDashboard();
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Erro ao excluir fatura: ' + error.message);
                    })
                    .finally(() => {
                        hideLoading();
                        fecharModal();
                    });
            }

            function formatarMoeda(valor) {
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(valor);
            }

            function formatarData(data) {
                if (!data) return '-';
                try {
                    const [ano, mes, dia] = data.split('-');
                    return `${dia}/${mes}/${ano}`;
                } catch (e) {
                    console.error('Erro ao formatar data:', data, e);
                    return data;
                }
            }

            function showLoading() {
                document.querySelector('.loading').style.display = 'flex';
            }

            function hideLoading() {
                document.querySelector('.loading').style.display = 'none';
            }

            // Carrega o dashboard inicial
            atualizarDashboard();
        });
    </script>
</body>
</html>