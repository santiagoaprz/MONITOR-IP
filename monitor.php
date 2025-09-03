<?php
// monitor.php
$config = require __DIR__ . '/config.php';
date_default_timezone_set('America/Mexico_City');

try {
    $pdo = new PDO(
        "mysql:host={$config->db_host};dbname={$config->db_name}",
        $config->db_user,
        $config->db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
} catch (Exception $e) {
    file_put_contents($config->logs_dir.'/error.log', date('c')." DB connect error: {$e->getMessage()}\n", FILE_APPEND);
    exit(1);
}

$timeout = intval($config->check_timeout);

// obtener hosts
$stmt = $pdo->query("SELECT * FROM hosts");
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $result = ($errno === 0 && $httpcode >= 200 && $httpcode < 400) 
            ? ['status'=>'up','response_ms'=>$t] 
            : ['status'=>'down','response_ms'=>$t?:null];
    } else {
        $cmd = sprintf('ping -n 1 -w %d %s', max(1000, $timeout*1000), escapeshellarg($host));
        $t0 = microtime(true);
        exec($cmd, $output, $retval);
        $t = round((microtime(true)-$t0)*1000);
        $result = ($retval === 0) 
            ? ['status'=>'up','response_ms'=>$t] 
            : ['status'=>'down','response_ms'=>$t];
    }

    // guardar en checks
    $ins = $pdo->prepare('INSERT INTO checks (host_id,host,type,status,response_ms,checked_at) VALUES (?,?,?,?,?,NOW())');
    $ins->execute([$host_id, $host, $type, $result['status'], $result['response_ms']]);

    // comparar con last_status
    $prev_status = $h['last_status'] ?? null;
    if ($prev_status !== $result['status']) {
        $up = $pdo->prepare('UPDATE hosts SET last_status = ?, last_change = NOW() WHERE id = ?');
        $up->execute([$result['status'], $host_id]);

        $message = "ALERTA: {$label} ({$host}) cambió: ".strtoupper($result['status'])." (antes: ".($prev_status??'desconocido').")";

        // enviar alerta Telegram
        if (!empty($config->telegram_token) && !empty($config->telegram_chat_id)) {
            $bot = $config->telegram_token;
            $chat = $config->telegram_chat_id;
            $text = urlencode($message . "\nHora: " . date('Y-m-d H:i:s'));
            @file_get_contents("https://api.telegram.org/bot{$bot}/sendMessage?chat_id={$chat}&text={$text}");
        }

        // log alerta
        file_put_contents($config->logs_dir . '/alerts.log', date('c')." - {$message}\n", FILE_APPEND);
    }

    // log general
    $line = date('Y-m-d H:i:s') . " | {$type} | {$host} | {$result['status']} | {$result['response_ms']}ms\n";
    file_put_contents($config->logs_dir . '/checks.log', $line, FILE_APPEND);
}

