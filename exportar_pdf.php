<?php
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verifica se está logado
$usuarioLogado = verificarLogin();

if (!isset($_GET['id'])) {
    die('ID não fornecido');
}

$id = $_GET['id'];
$container = lerContainer($id);
$planInner = lerPlanInner($id);

if (!$container) {
    die('Container não encontrado');
}

$tempoDescarga = calcularTempoDescarga($container['horario_inicio'], $container['horario_fim']);
$custoTotal = calcularCustoDescarga($container['qtd_trabalhadores'], $container['valor_trabalhador']);

// Cria o HTML do relatório
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .section { margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .metric { background: #f3f4f6; padding: 10px; border-radius: 5px; }
        .metric h3 { margin: 0; color: #1f2937; }
        .metric p { margin: 5px 0 0; font-size: 1.5em; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detalhes do Container: ' . htmlspecialchars($container['ordem_descarga']) . '</h1>
        <p>Registrado por ' . htmlspecialchars($container['usuario']) . ' em ' . date('d/m/Y', strtotime($container['data'])) . '</p>
    </div>

    <div class="section">
        <div class="grid">
            <div class="metric">
                <h3>Tempo de Descarga</h3>
                <p>' . number_format($tempoDescarga, 1) . ' horas</p>
            </div>
            <div class="metric">
                <h3>Custo Total</h3>
                <p>R$ ' . number_format($custoTotal, 2, ',', '.') . '</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Informações do Container</h2>
        <p><strong>Número do Container:</strong> ' . htmlspecialchars($container['numero_container']) . '</p>
        <p><strong>Nota Fiscal:</strong> ' . htmlspecialchars($container['numero_nf']) . '</p>
        <p><strong>Origem:</strong> ' . htmlspecialchars($container['origem']) . '</p>
        <p><strong>Tipo de Produto:</strong> ' . htmlspecialchars($container['tipo_produto']) . '</p>
    </div>

    <div class="section">
        <h2>Informações da Descarga</h2>
        <p><strong>Horário de Início:</strong> ' . substr($container['horario_inicio'], 0, 5) . '</p>
        <p><strong>Horário de Término:</strong> ' . substr($container['horario_fim'], 0, 5) . '</p>
        <p><strong>Valor por Trabalhador:</strong> R$ ' . number_format($container['valor_trabalhador'], 2, ',', '.') . '</p>
        <p><strong>Uso de Esteira:</strong> ' . ($container['usa_esteira'] == '1' ? 'SIM' : 'NÃO') . '</p>
    </div>';

if ($container['avarias']) {
    $html .= '
    <div class="section">
        <h2>Avarias</h2>
        <p>' . nl2br(htmlspecialchars($container['avarias'])) . '</p>
    </div>';
}

if ($container['observacoes']) {
    $html .= '
    <div class="section">
        <h2>Observações</h2>
        <p>' . nl2br(htmlspecialchars($container['observacoes'])) . '</p>
    </div>';
}

// Adiciona a tabela de itens em uma nova página
if (!empty($planInner)) {
    $html .= '
    <div class="page-break">
        <h2>Itens do Container</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Código de Barras</th>
                    <th>Descrição</th>
                    <th>CX</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($planInner as $item) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['codigo']) . '</td>
                    <td>' . htmlspecialchars($item['cod_barras']) . '</td>
                    <td>' . htmlspecialchars($item['descricao']) . '</td>
                    <td>' . htmlspecialchars($item['cx']) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </div>';
}

$html .= '
</body>
</html>';

// Configura o DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Gera o arquivo PDF
$dompdf->stream("container_" . $container['ordem_descarga'] . ".pdf", array("Attachment" => true));
