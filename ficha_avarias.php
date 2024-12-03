<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'funcoes_auth.php';
require_once 'vendor/autoload.php';

$usuario_logado = verificarLogin();

if (!isset($_GET['id'])) {
    die('ID do container não especificado');
}

$container_id = $_GET['id'];
$container_file = "dados/containers/$container_id.json";
$avarias_file = "dados/avarias/$container_id.txt";

if (!file_exists($container_file)) {
    die('Arquivo do container não encontrado: ' . $container_file);
}

if (!file_exists($avarias_file)) {
    die('Arquivo de avarias não encontrado: ' . $avarias_file);
}

try {
    $container = json_decode(file_get_contents($container_file), true);
    if ($container === null) {
        throw new Exception('Erro ao decodificar JSON do container: ' . json_last_error_msg());
    }

    $avarias = [];
    $linhas = file($avarias_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $avaria = json_decode($linha, true);
        if ($avaria !== null) {
            $avarias[] = $avaria;
        }
    }

    // Configuração do MPDF
    $mpdf = new \Mpdf\Mpdf([
        'orientation' => 'L',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'tempDir' => sys_get_temp_dir()
    ]);

    $html = '
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12pt;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .container-info { 
            margin-bottom: 30px; 
        }
        .avaria { 
            margin-bottom: 20px; 
            padding: 10px; 
            border: 1px solid #ddd;
            page-break-inside: avoid;
            background-color: #f9f9f9;
        }
        .fotos { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px;
            margin-top: 10px;
        }
        .foto { 
            width: 200px; 
            height: auto; 
            margin: 5px;
            border: 1px solid #ddd;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 10px;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
        }
        th { 
            background-color: #f4f4f4; 
            font-weight: bold;
            width: 20%;
        }
        .footer {
            text-align: right;
            font-size: 10pt;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        h1 { font-size: 18pt; margin: 0; padding: 0; }
        h2 { font-size: 16pt; margin: 10px 0; padding: 0; }
        h3 { font-size: 14pt; margin: 15px 0; padding: 0; }
    </style>

    <div class="header">
        <h1>RELATÓRIO DE AVARIAS</h1>
        <h2>Container ' . htmlspecialchars($container['numero']) . ' - Ordem: ' . htmlspecialchars($container['ordem']) . '</h2>
    </div>

    <div class="container-info">
        <table>
            <tr>
                <th>Data de Recebimento:</th>
                <td>' . date('d/m/Y', strtotime($container['data'])) . '</td>
                <th>Nota Fiscal:</th>
                <td>' . htmlspecialchars($container['nota_fiscal']) . '</td>
            </tr>
            <tr>
                <th>Origem:</th>
                <td>' . htmlspecialchars($container['origem']) . '</td>
                <th>Tipo de Mercadoria:</th>
                <td>' . htmlspecialchars($container['tipo_mercadoria']) . '</td>
            </tr>
            <tr>
                <th>Empresa:</th>
                <td>' . htmlspecialchars($container['empresa']) . '</td>
                <th>Total de Avarias:</th>
                <td><strong>' . count($avarias) . '</strong></td>
            </tr>
        </table>
    </div>

    <h3>Avarias Registradas</h3>';

    foreach ($avarias as $index => $avaria) {
        $html .= '
        <div class="avaria">
            <table>
                <tr>
                    <th>Avaria Nº:</th>
                    <td colspan="3"><strong>' . ($index + 1) . '</strong></td>
                </tr>
                <tr>
                    <th>Código:</th>
                    <td>' . htmlspecialchars($avaria['codigo']) . '</td>
                    <th>Descrição:</th>
                    <td>' . htmlspecialchars($avaria['descricao']) . '</td>
                </tr>
                <tr>
                    <th>Tipo de Avaria:</th>
                    <td>' . htmlspecialchars($avaria['tipo_avaria']) . '</td>
                    <th>Quantidade:</th>
                    <td>' . htmlspecialchars($avaria['quantidade']) . ' ' . htmlspecialchars($avaria['unidade']) . '</td>
                </tr>
                <tr>
                    <th>Data do Registro:</th>
                    <td>' . date('d/m/Y H:i', strtotime($avaria['data_registro'])) . '</td>
                    <th>Registrado por:</th>
                    <td>' . htmlspecialchars($avaria['usuario']) . '</td>
                </tr>
                <tr>
                    <th>Observação:</th>
                    <td colspan="3">' . nl2br(htmlspecialchars($avaria['observacao'])) . '</td>
                </tr>
            </table>';

        if (!empty($avaria['fotos'])) {
            $html .= '<div class="fotos">';
            foreach ($avaria['fotos'] as $foto) {
                $path = 'dados/fotos_avarias/' . $foto;
                if (file_exists($path)) {
                    $tipo = pathinfo($path, PATHINFO_EXTENSION);
                    $dados = file_get_contents($path);
                    $base64 = base64_encode($dados);
                    $html .= '<img src="data:image/' . $tipo . ';base64,' . $base64 . '" class="foto">';
                }
            }
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '
    <div class="footer">
        <p>Relatório gerado em: ' . date('d/m/Y H:i:s') . '<br>
        por: ' . htmlspecialchars($usuario_logado['nome']) . '</p>
    </div>';

    // Configurações do PDF
    $mpdf->SetTitle('Relatório de Avarias - Container ' . $container['numero']);
    $mpdf->SetAuthor($usuario_logado['nome']);
    $mpdf->SetCreator('Sistema de Gestão de Containers');
    
    // Adiciona marca d'água
    $mpdf->SetWatermarkText('RELATÓRIO DE AVARIAS');
    $mpdf->showWatermarkText = true;
    $mpdf->watermarkTextAlpha = 0.1;

    // Gera o PDF
    $mpdf->WriteHTML($html);
    $mpdf->Output('avarias_' . $container_id . '.pdf', 'I');

} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}
?>