<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'master') {
    header("Location: index.php");
    exit();
}

$logs = [];

// Carrega os logs do arquivo logs.txt
if (file_exists('dados/logs.txt')) {
    $logs = file('dados/logs.txt');
}

// Função para exportar os logs para CSV
if (isset($_POST['exportar_csv'])) {
    $arquivo_csv = fopen('logs_export.csv', 'w');
    fputcsv($arquivo_csv, ['Data', 'Usuário', 'Ação', 'Descrição']);
    foreach ($logs as $log) {
        $log_dados = explode('|', $log);
        fputcsv($arquivo_csv, $log_dados);
    }
    fclose($arquivo_csv);
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="logs_export.csv"');
    readfile('logs_export.csv');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Logs - Sistema de Indicadores CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">
    <h2>Relatórios de Logs</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Data</th>
                <th>Usuário</th>
                <th>Ação</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <?php
                $log_dados = explode('|', $log);
                ?>
                <tr>
                    <td><?php echo $log_dados[0]; ?></td>
                    <td><?php echo $log_dados[1]; ?></td>
                    <td><?php echo $log_dados[2]; ?></td>
                    <td><?php echo $log_dados[3]; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <form action="" method="POST">
        <button type="submit" name="exportar_csv" class="btn btn-success">Exportar para CSV</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
