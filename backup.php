p<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'master') {
    header("Location: index.php");
    exit();
}

// Função para criar um arquivo zip do diretório
function zipDir($dirPath, $zipFile) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dirPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return true;
    } else {
        return false;
    }
}

// Define o caminho do diretório e nome do arquivo zip
$dirPath = __DIR__;
$zipFile = 'backup_sistema_' . date('Y-m-d') . '.zip';

// Chama a função para zipar o diretório
if (zipDir($dirPath, $zipFile)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFile . '"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    unlink($zipFile); // Exclui o arquivo zip após o download
    exit();
} else {
    echo "Erro ao criar o arquivo de backup.";
}
?>
