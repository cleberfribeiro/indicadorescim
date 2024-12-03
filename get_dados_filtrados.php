<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Sao_Paulo');

require_once 'separacao.php';

$periodo = $_GET['periodo'] ?? 'dia';
$dataInicio = $_GET['dataInicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['dataFim'] ?? date('Y-m-d');

$painel = new PainelSeparacao();
$dados = $painel->getDadosFiltrados($periodo, $dataInicio, $dataFim);

echo json_encode($dados);