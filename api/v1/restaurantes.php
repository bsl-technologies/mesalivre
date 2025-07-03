<?php
// api/v1/restaurantes.php

$database = getDbConnection();
$restaurante = new Restaurante($database);

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$tokenPayload = AuthMiddleware::validateToken($authHeader);

if ($method === 'POST') {
    error_log("LOG: Método POST detectado para criar restaurante.");

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'restaurante') && !AuthMiddleware::hasRole($tokenPayload, 'admin')) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para criar um restaurante.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (
        empty($data->nome) ||
        empty($data->endereco) ||
        empty($data->cidade) ||
        empty($data->culinaria) ||
        empty($data->horarios_funcionamento)
    ) {
        Response::json(400, ['message' => 'Dados incompletos para criar o restaurante. Campos obrigatórios: nome, endereco, culinaria, horarios_funcionamento.']);
    }

    $restaurante->nome = $data->nome;
    $restaurante->endereco = $data->endereco;
    $restaurante->cidade = $data->cidade;
    $restaurante->telefone = $data->telefone ?? null;
    $restaurante->culinaria = $data->culinaria;
    $restaurante->horarios_funcionamento = $data->horarios_funcionamento;
    $restaurante->descricao = $data->descricao ?? null;
    $restaurante->fotos = isset($data->fotos) ? json_encode($data->fotos) : null;
    $restaurante->usuario_id = $tokenPayload['uid'];

    if ($restaurante->criar()) {
        Response::json(201, ['message' => 'Restaurante criado com sucesso!']);
    } else {
        if ($restaurante->nomeExiste()) {
             Response::json(409, ['message' => 'Um restaurante com este nome já existe.']);
        }
        Response::json(500, ['message' => 'Não foi possível criar o restaurante. Erro interno do servidor.']);
    }
}

elseif ($method === 'GET' && $resourceId === null) {
    error_log("LOG: Método GET detectado para listar restaurantes.");

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    $busca = $_GET['busca'] ?? '';
    $culinaria = $_GET['culinaria'] ?? '';
    $cidade = $_GET['cidade'] ?? '';

    $restaurantes_data = $restaurante->ler($offset, $limit, $busca, $culinaria, $cidade);

    foreach ($restaurantes_data as &$item) {
        $item['fotos'] = ($item['fotos'] !== null) ? json_decode($item['fotos']) : null;
    }

    $total_registros = $restaurante->contar($busca, $culinaria, $cidade);

    Response::json(200, [
        'data' => $restaurantes_data,
        'pagina_atual' => $page,
        'total_paginas' => ceil($total_registros / $limit),
        'total_registros' => $total_registros
    ]);
}

elseif ($method === 'GET' && $resourceId !== null) {
    error_log("LOG: Método GET detectado para obter restaurante por ID: " . $resourceId);

    $restaurante->id = $resourceId;

    if ($restaurante->lerUm()) {
        $restaurante_arr = [
            "id" => $restaurante->id,
            "nome" => $restaurante->nome,
            "endereco" => $restaurante->endereco,
            "cidade" => $restaurante->cidade,
            "telefone" => $restaurante->telefone,
            "culinaria" => $restaurante->culinaria,
            "horarios_funcionamento" => $restaurante->horarios_funcionamento,
            "descricao" => $restaurante->descricao,
            "fotos" => ($restaurante->fotos !== null) ? json_decode($restaurante->fotos) : null,
            "usuario_id" => $restaurante->usuario_id,
            "data_criacao" => $restaurante->data_criacao,
            "data_atualizacao" => $restaurante->data_atualizacao
        ];
        Response::json(200, $restaurante_arr);
    } else {
        Response::json(404, ['message' => 'Restaurante não encontrado.']);
    }
}

elseif ($method === 'PUT' && $resourceId !== null) {
    error_log("LOG: Método PUT detectado para atualizar restaurante por ID: " . $resourceId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $restaurante->id = $resourceId;
    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$restaurante->isOwner($tokenPayload['uid'])) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar este restaurante.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (
        empty($data->nome) ||
        empty($data->endereco) ||
        empty($data->cidade) ||
        empty($data->culinaria) ||
        empty($data->horarios_funcionamento)
    ) {
        Response::json(400, ['message' => 'Dados incompletos para atualizar o restaurante. Campos obrigatórios para PUT: nome, endereco, culinaria, horarios_funcionamento.']);
    }

    $restaurante->id = $resourceId;
    $restaurante->nome = $data->nome;
    $restaurante->endereco = $data->endereco;
    $restaurante->cidade = $data->cidade;
    $restaurante->telefone = $data->telefone ?? null;
    $restaurante->culinaria = $data->culinaria;
    $restaurante->horarios_funcionamento = $data->horarios_funcionamento;
    $restaurante->descricao = $data->descricao ?? null;
    $restaurante->fotos = isset($data->fotos) ? json_encode($data->fotos) : null;

    if ($restaurante->atualizar()) {
        Response::json(200, ['message' => 'Restaurante atualizado com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível atualizar o restaurante.']);
    }
}

elseif ($method === 'PATCH' && $resourceId !== null) {
    error_log("LOG: Método PATCH detectado para atualizar restaurante por ID: " . $resourceId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $restaurante->id = $resourceId;
    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$restaurante->isOwner($tokenPayload['uid'])) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar este restaurante.']);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data)) {
        Response::json(400, ['message' => 'Nenhum dado fornecido para atualização parcial.']);
    }

    if (isset($data['fotos']) && is_array($data['fotos'])) {
        $data['fotos'] = json_encode($data['fotos']);
    }

    unset($data['id']);
    unset($data['usuario_id']);
    unset($data['data_criacao']);
    unset($data['data_atualizacao']);

    $restaurante->id = $resourceId;

    if ($restaurante->atualizarParcialmente($data)) {
        Response::json(200, ['message' => 'Restaurante atualizado parcialmente com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível atualizar o restaurante parcialmente.']);
    }
}

elseif ($method === 'DELETE' && $resourceId !== null) {
    error_log("LOG: Método DELETE detectado para remover restaurante por ID: " . $resourceId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $restaurante->id = $resourceId;
    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$restaurante->isOwner($tokenPayload['uid'])) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para deletar este restaurante.']);
    }

    if ($restaurante->deletar()) {
        Response::json(200, ['message' => 'Restaurante deletado com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível deletar o restaurante.']);
    }
}

else {
    error_log("LOG: Método ou endpoint não permitido detectado para restaurantes.");
    Response::json(405, ['message' => 'Método ou endpoint não permitido para restaurantes.']);
}

error_log("LOG: --- Fim de restaurantes.php ---");
