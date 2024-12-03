<?php
require 'vendor/autoload.php';
require_once 'separacao.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExportadorDados {
    private $painel;

    public function __construct() {
        $this->painel = new PainelSeparacao();
    }

    public function exportar($tipo = 'atual', $dataInicio = null, $dataFim = null) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Configuração inicial
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle('A1:J1')->getFont()->getColor()->setRGB('FFFFFF');

        // Cabeçalhos
        $headers = [
            'Data', 'Pedido', 'Cliente', 'UF', 'Cidade',
            'Status', 'Volume', 'Peso', 'Transportadora', 'Observações'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Dados
        $dados = $this->painel->getDados();
        $row = 2;
        
        foreach ($dados as $dado) {
            $sheet->setCellValue('A' . $row, $dado['Solicitado Separacao'] ?? '');
            $sheet->setCellValue('B' . $row, $dado['P.Venda'] ?? '');
            $sheet->setCellValue('C' . $row, $dado['RzSocial'] ?? '');
            $sheet->setCellValue('D' . $row, $dado['UF'] ?? '');
            $sheet->setCellValue('E' . $row, $dado['Cidade'] ?? '');
            $sheet->setCellValue('F' . $row, $dado['Status Separacao'] ?? '');
            $sheet->setCellValue('G' . $row, $dado['Vols'] ?? '');
            $sheet->setCellValue('H' . $row, $dado['Peso Bruto'] ?? '');
            $sheet->setCellValue('I' . $row, $dado['Transp'] ?? '');
            $sheet->setCellValue('J' . $row, $dado['OBS Separacao'] ?? '');

            // Estilo condicional baseado no status
            $status = strtoupper($dado['Status Separacao'] ?? '');
            $corFundo = match($status) {
                'SEPARADO' => 'C6F6D5',
                'A SEPARAR' => 'FEF3C7',
                'SOLICITADO SEP' => 'E9D5FF',
                default => 'FFFFFF'
            };

            $sheet->getStyle('A'.$row.':J'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($corFundo);

            $row++;
        }

        // Adicionar bordas
        $lastRow = $row - 1;
        $sheet->getStyle('A1:J'.$lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Adicionar filtros
        $sheet->setAutoFilter('A1:J'.$lastRow);

        // Gerar arquivo
        $writer = new Xlsx($spreadsheet);
        
        // Headers para download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="relatorio_separacao_'.date('Y-m-d_His').'.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}

// Processar requisição de exportação
if (isset($_GET['tipo'])) {
    $exportador = new ExportadorDados();
    $tipo = $_GET['tipo'];
    $dataInicio = $_GET['dataInicio'] ?? null;
    $dataFim = $_GET['dataFim'] ?? null;
    
    $exportador->exportar($tipo, $dataInicio, $dataFim);
}