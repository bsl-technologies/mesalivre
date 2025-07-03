<?php
// config/database.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'mesalivre'); // Substitua pelo nome do seu banco de dados
define('DB_USER', 'root');      // Substitua pelo seu usuário do MySQL
define('DB_PASS', 'shaman');        // Substitua pela sua senha do MySQL

function getDbConnection() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna resultados como array associativo
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Desativa a emulação de prepared statements para segurança
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Em um ambiente de produção, logue o erro e mostre uma mensagem genérica.
        // error_log('Erro de conexão com o banco de dados: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['message' => 'Erro interno do servidor.']);
        exit();
    }
}