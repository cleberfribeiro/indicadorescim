<?php
class Logger {
    private $logFile;
    private $maxFileSize = 5242880; // 5MB
    private $maxFiles = 5;
    private $logLevels = [
        'ERROR'   => 1,
        'WARNING' => 2,
        'INFO'    => 3,
        'DEBUG'   => 4
    ];

    public function __construct($logFile = 'dados/logs.txt') {
        $this->logFile = $logFile;
        
        // Cria diretório de logs se não existir
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    /**
     * Registra uma mensagem de log
     * @param string $message Mensagem a ser registrada
     * @param string $level Nível do log (ERROR, WARNING, INFO, DEBUG)
     * @param array $context Dados adicionais para o log
     */
    public function log($message, $level = 'INFO', $context = []) {
        $this->rotateLogIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);
        $user = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Formata a mensagem de log
        $logMessage = sprintf(
            "[%s] [%s] [User: %s] [IP: %s] %s",
            $timestamp,
            $level,
            $user,
            $ip,
            $message
        );

        // Adiciona contexto se existir
        if (!empty($context)) {
            $logMessage .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $logMessage .= PHP_EOL;

        // Escreve no arquivo de log
        if (!file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX)) {
            error_log("Erro ao escrever no arquivo de log: " . $this->logFile);
        }
    }

    /**
     * Métodos específicos para diferentes níveis de log
     */
    public function error($message, $context = []) {
        $this->log($message, 'ERROR', $context);
    }

    public function warning($message, $context = []) {
        $this->log($message, 'WARNING', $context);
    }

    public function info($message, $context = []) {
        $this->log($message, 'INFO', $context);
    }

    public function debug($message, $context = []) {
        $this->log($message, 'DEBUG', $context);
    }

    /**
     * Rotaciona os arquivos de log se necessário
     */
    private function rotateLogIfNeeded() {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) >= $this->maxFileSize) {
            for ($i = $this->maxFiles - 1; $i > 0; $i--) {
                $oldFile = sprintf("%s.%d", $this->logFile, $i);
                $newFile = sprintf("%s.%d", $this->logFile, $i + 1);
                
                if (file_exists($oldFile)) {
                    rename($oldFile, $newFile);
                }
            }

            rename($this->logFile, $this->logFile . ".1");
            
            // Limpa logs antigos
            if (file_exists($this->logFile . "." . ($this->maxFiles + 1))) {
                unlink($this->logFile . "." . ($this->maxFiles + 1));
            }
        }
    }

    /**
     * Limpa logs antigos baseado em data
     * @param int $days Número de dias para manter
     */
    public function clearOldLogs($days = 30) {
        $files = glob($this->logFile . "*");
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Busca logs por critérios
     * @param array $criteria Critérios de busca
     * @return array Logs encontrados
     */
    public function searchLogs($criteria = []) {
        $logs = [];
        
        if (!file_exists($this->logFile)) {
            return $logs;
        }

        $handle = fopen($this->logFile, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $matched = true;
                
                foreach ($criteria as $key => $value) {
                    if (stripos($line, $value) === false) {
                        $matched = false;
                        break;
                    }
                }
                
                if ($matched) {
                    $logs[] = trim($line);
                }
            }
            fclose($handle);
        }

        return $logs;
    }

    /**
     * Exporta logs para CSV
     * @param string $outputFile Nome do arquivo de saída
     * @param array $criteria Critérios de filtro opcional
     * @return bool Sucesso da operação
     */
    public function exportToCSV($outputFile, $criteria = []) {
        $logs = $this->searchLogs($criteria);
        
        if (empty($logs)) {
            return false;
        }

        $fp = fopen($outputFile, 'w');
        if (!$fp) {
            return false;
        }

        // Cabeçalho CSV
        fputcsv($fp, ['Data/Hora', 'Nível', 'Usuário', 'IP', 'Mensagem', 'Contexto']);

        foreach ($logs as $log) {
            // Parse do log
            if (preg_match('/\[(.*?)\] \[(.*?)\] \[User: (.*?)\] \[IP: (.*?)\] (.*?)(?:\| Context: (.*))?$/', $log, $matches)) {
                fputcsv($fp, [
                    $matches[1], // Data/Hora
                    $matches[2], // Nível
                    $matches[3], // Usuário
                    $matches[4], // IP
                    $matches[5], // Mensagem
                    $matches[6] ?? '' // Contexto
                ]);
            }
        }

        fclose($fp);
        return true;
    }
}

// Exemplo de uso:
$logger = new Logger('dados/logs.txt');

// Registrar diferentes tipos de logs
function registrar_log($mensagem, $nivel = 'INFO', $contexto = []) {
    global $logger;
    $logger->log($mensagem, $nivel, $contexto);
}

// Exemplos de uso:
/*
registrar_log("Usuário fez login", "INFO", ['username' => 'usuario123']);
registrar_log("Tentativa de acesso negada", "WARNING", ['ip' => '192.168.1.1']);
registrar_log("Erro na base de dados", "ERROR", ['erro' => 'Conexão falhou']);
registrar_log("Debug de função", "DEBUG", ['função' => 'processar_dados']);

// Buscar logs
$logs = $logger->searchLogs(['ERROR', 'database']);

// Exportar logs
$logger->exportToCSV('logs_export.csv', ['WARNING']);

// Limpar logs antigos
$logger->clearOldLogs(30);
*/
?>
