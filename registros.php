<?php
$dados_dir = __DIR__ . '/dados'; // Diretório dos dados
$registros = [];

// Carregar registros
$arquivo = $dados_dir . '/registros_diarios.txt';
if (file_exists($arquivo)) {
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        list($data, $total, $solicitados, $separados, $prontos) = explode(',', $linha);
        $registros[] = [
            'data' => $data,
            'total' => (int)$total,
            'solicitados' => (int)$solicitados,
            'separados' => (int)$separados,
            'prontos' => (int)$prontos,
        ];
    }
}

// Identificar maior e menor quantidade de pedidos
$diasMaisPedidos = null;
$diasMenosPedidos = null;

if (!empty($registros)) {
    foreach ($registros as $registro) {
        if ($diasMaisPedidos === null || $registro['total'] > $diasMaisPedidos['total']) {
            $diasMaisPedidos = $registro;
        }
        if ($diasMenosPedidos === null || $registro['total'] < $diasMenosPedidos['total']) {
            $diasMenosPedidos = $registro;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Separação</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container mt-4">
    <h1 class="text-center mb-4"><i class="fas fa-calendar-alt text-primary"></i> Registros de Separação</h1>

    <!-- Informações de Maior/Menor Dia -->
    <div class="row mb-4">
        <?php if ($diasMaisPedidos): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-arrow-up"></i> Maior Quantidade de Pedidos
                    </div>
                    <div class="card-body">
                        <p><strong>Data:</strong> <?php echo htmlspecialchars($diasMaisPedidos['data']); ?></p>
                        <p><strong>Total:</strong> <?php echo htmlspecialchars($diasMaisPedidos['total']); ?> pedidos</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($diasMenosPedidos): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <i class="fas fa-arrow-down"></i> Menor Quantidade de Pedidos
                    </div>
                    <div class="card-body">
                        <p><strong>Data:</strong> <?php echo htmlspecialchars($diasMenosPedidos['data']); ?></p>
                        <p><strong>Total:</strong> <?php echo htmlspecialchars($diasMenosPedidos['total']); ?> pedidos</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabela de Registros -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-list"></i> Registros Detalhados
        </div>
        <div class="card-body">
            <?php if (!empty($registros)): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Data e Hora</th>
                            <th>Total Pedidos</th>
                            <th>Pedidos Solicitados</th>
                            <th>Pedidos Separados</th>
                            <th>Pedidos Prontos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $registro): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($registro['data']); ?></td>
                                <td><?php echo htmlspecialchars($registro['total']); ?></td>
                                <td><?php echo htmlspecialchars($registro['solicitados']); ?></td>
                                <td><?php echo htmlspecialchars($registro['separados']); ?></td>
                                <td><?php echo htmlspecialchars($registro['prontos']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">Nenhum registro encontrado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botão para voltar -->
    <div class="mt-4 text-center">
        <a href="separação.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a Página Principal
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
