<?php
// auto_monitor.php
$config = require __DIR__ . '/config.php';
date_default_timezone_set('America/Mexico_City');

// comprobar carpetas
if (!file_exists($config['logs_dir'])) mkdir($config['logs_dir'], 0777, true);
if (!file_exists($config['reports_dir'])) mkdir($config['reports_dir'], 0777, true);

// Conexión DB
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']}",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    file_put_contents($config['logs_dir'].'/error.log', date('c')." DB connect error: ".$e->getMessage()."\n", FILE_APPEND);
    exit(1);
}

// hosts
$stmt = $pdo->query("SELECT * FROM hosts");
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$timeout = intval($config['check_timeout']);

// horario de generación de PDF
$pdf_hours = [6,10,14,18,22,2]; 
$last_pdf_hour = null;
$first_run = true; // variable para generar PDF al primer chequeo

while (true) {
    $now = new DateTime();
    $hour = intval($now->format('G'));

    // Verificación de hosts
    foreach ($hosts as $h) {
        $host_id = $h['id'];
        $host = $h['host'];
        $type = $h['type'];
        $label = $h['label'] ?: $host;

        $result = ['status'=>'down','response_ms'=>null];

        if ($type === 'web') {
            $url = $host;
            if (!preg_match('#^https?://#', $url)) $url = 'https://' . $url;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $t0 = microtime(true);
            curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $t = round((microtime(true)-$t0)*1000);
            curl_close($ch);
            if ($errno === 0 && $httpcode >= 200 && $httpcode < 400) {
                $result = ['status'=>'up','response_ms'=>$t];
            }
        } else {
            $cmd = sprintf('ping -n 1 -w %d %s', max(1000, $timeout*1000), escapeshellarg($host));
            $t0 = microtime(true);
            exec($cmd, $output, $retval);
            $t = round((microtime(true)-$t0)*1000);
            if ($retval === 0) $result = ['status'=>'up','response_ms'=>$t];
        }

        // guardar en checks
        $ins = $pdo->prepare('INSERT INTO checks (host_id,host,type,status,response_ms,checked_at) VALUES (?,?,?,?,?,NOW())');
        $ins->execute([$host_id,$host,$type,$result['status'],$result['response_ms']]);

        // alertas
        $prev_status = $h['last_status'] ?: null;
        if ($prev_status !== $result['status']) {
            $up = $pdo->prepare('UPDATE hosts SET last_status = ?, last_change = NOW() WHERE id = ?');
            $up->execute([$result['status'],$host_id]);

            $message = "ALERTA: {$label} ({$host}) cambió: ".strtoupper($result['status'])." (antes: ".($prev_status?:'desconocido').")";

            if (!empty($config['telegram_token']) && !empty($config['telegram_chat_id'])) {
                $bot = $config['telegram_token'];
                $chat = $config['telegram_chat_id'];
                $text = urlencode($message . "\nHora: ".date('Y-m-d H:i:s'));
                $url = "https://api.telegram.org/bot{$bot}/sendMessage?chat_id={$chat}&text={$text}";
                @file_get_contents($url);
            }

            file_put_contents($config['logs_dir'].'/alerts.log', date('c')." - {$message}\n", FILE_APPEND);
        }

        // log general
        $line = date('Y-m-d H:i:s')." | {$type} | {$host} | {$result['status']} | {$result['response_ms']}ms\n";
        file_put_contents($config['logs_dir'].'/checks.log', $line, FILE_APPEND);
    }

    // generar PDF al primer chequeo o cada hora específica
    if ($first_run || (in_array($hour, $pdf_hours) && $last_pdf_hour !== $hour)) {
        require __DIR__.'/generate_pdf.php'; // usa require, no carga fpdf.php directo
        $last_pdf_hour = $hour;
        $first_run = false; // ya generó PDF inicial
    }

    // esperar 60 segundos
    sleep(60);
}
