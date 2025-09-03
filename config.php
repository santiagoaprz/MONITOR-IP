<?php
// config.php

return [
    // Configuración de la base de datos
    'db_host' => '127.0.0.1',      // localhost o IP del servidor MySQL
    'db_name' => 'monitor',     // nombre de tu base de datos
    'db_user' => 'root',            // usuario MySQL
    'db_pass' => '',                // contraseña MySQL

    // Configuración de directorios
    'logs_dir' => __DIR__ . '/logs',       // carpeta para logs
    'reports_dir' => __DIR__ . '/reports', // carpeta para PDFs

    // Configuración de monitoreo
    'check_timeout' => 5, // tiempo máximo en segundos para ping/curl

    // Configuración de Telegram (opcional)
    'telegram_token' => '',      // token del bot de Telegram
    'telegram_chat_id' => '',    // chat ID para recibir alertas
];
