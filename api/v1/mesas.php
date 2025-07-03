<?php
// api/v1/mesas.php

error_log("LOG: --- Inicio de mesas.php ---");

$database = getDbConnection();
$mesa = new Mesa($database);
$restaurante_model = new Restaurante($database); 

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$tokenPayload = AuthMiddleware::validateToken($authHeader);

$restauranteId = $resourceId;
$mesaId = $secondaryResourceId; 

if ($method === 'POST' && $restauranteId !== null) {
    error_log("LOG: Método POST detectado para criar mesa no restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'restaurante') && !AuthMiddleware::hasRole($tokenPayload, 'admin')) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para criar mesas.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$restaurante_model->isOwner($tokenPayload['uid'], $restauranteId)) {
        Response::json(403, ['message' => 'Acesso negado. Você não é o proprietário deste restaurante.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->numero) || empty($data->capacidade)) {
        Response::json(400, ['message' => 'Dados incompletos para criar a mesa. Campos obrigatórios: numero, capacidade.']);
    }

    $mesa->numero = $data->numero;
    $mesa->capacidade = $data->capacidade;
    $mesa->restaurante_id = $restauranteId;
    $mesa->status = $data->status ?? 'disponivel';

    if ($mesa->criar()) {
        Response::json(201, ['message' => 'Mesa criada com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível criar a mesa.']);
    }
}

elseif ($method === 'GET' && $restauranteId !== null && $mesaId === null) {
    error_log("LOG: Método GET detectado para listar mesas do restaurante: " . $restauranteId);

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    $status_filter = $_GET['status'] ?? null;

    $mesas_data = $mesa->ler($restauranteId, $offset, $limit, $status_filter);
    $total_registros = $mesa->contar($restauranteId, $status_filter);

    Response::json(200, [
        'data' => $mesas_data,
        'pagina_atual' => $page,
        'total_paginas' => ceil($total_registros / $limit),
        'total_registros' => $total_registros
    ]);
}

elseif ($method === 'GET' && $restauranteId !== null && $mesaId !== null) {
    error_log("LOG: Método GET detectado para obter mesa: " . $mesaId . " do restaurante: " . $restauranteId);

    $mesa->id = $mesaId;
    $mesa->restaurante_id = $restauranteId;

    if ($mesa->lerUma()) {
        $mesa_arr = [
            "id" => $mesa->id,
            "numero" => $mesa->numero,
            "capacidade" => $mesa->capacidade,
            "restaurante_id" => $mesa->restaurante_id,
            "status" => $mesa->status,
            "data_criacao" => $mesa->data_criacao,
            "data_atualizacao" => $mesa->data_atualizacao
        ];
        Response::json(200, $mesa_arr);
    } else {
        Response::json(404, ['message' => 'Mesa não encontrada para este restaurante.']);
    }
}

elseif ($method === 'PUT' && $restauranteId !== null && $mesaId !== null) {
    error_log("LOG: Método PUT detectado para atualizar mesa: " . $mesaId . " do restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'restaurante') && !AuthMiddleware::hasRole($tokenPayload, 'admin')) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar mesas.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$restaurante_model->isOwner($tokenPayload['uid'], $restauranteId)) {
        Response::json(403, ['message' => 'Acesso negado. Você não é o proprietário deste restaurante.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->numero) || empty($data->capacidade) || empty($data->status)) {
        Response::json(400, ['message' => 'Dados incompletos para atualizar a mesa. Campos obrigatórios: numero, capacidade, status.']);
    }

    $mesa->id = $mesaId;
    $mesa->restaurante_id = $restauranteId;
    $mesa->numero = $data->numero;
    $mesa->capacidade = $data->capacidade;
    $mesa->status = $data->status;

    if ($mesa->atualizar()) {
        Response::json(200, ['message' => 'Mesa atualizada com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível atualizar a mesa.']);
    }
}

elseif ($method === 'PATCH' && $restauranteId !== null && $mesaId !== null) {
    error_log("LOG: Método PATCH detectado para atualizar mesa: " . $mesaId . " do restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'restaurante') && !AuthMiddleware::hasRole($tokenPayload, 'admin')) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar mesas.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$restaurante_model->isOwner($tokenPayload['uid'], $restauranteId)) {
        Response::json(403, ['message' => 'Acesso negado. Você não é o proprietário deste restaurante.']);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data)) {
        Response::json(400, ['message' => 'Nenhum dado fornecido para atualização parcial da mesa.']);
    }

    $mesa->id = $mesaId;
    $mesa->restaurante_id = $restauranteId;

    unset($data['id']);
    unset($data['restaurante_id']);
    unset($data['data_criacao']);
    unset($data['data_atualizacao']);

    if ($mesa->atualizarParcialmente($data)) {
        Response::json(200, ['message' => 'Mesa atualizada parcialmente com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível atualizar a mesa parcialmente.']);
    }
}

elseif ($method === 'DELETE' && $restauranteId !== null && $mesaId !== null) {
    error_log("LOG: Método DELETE detectado para deletar mesa: " . $mesaId . " do restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'restaurante') && !AuthMiddleware::hasRole($tokenPayload, 'admin')) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para deletar mesas.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$restaurante_model->isOwner($tokenPayload['uid'], $restauranteId)) {
        Response::json(403, ['message' => 'Acesso negado. Você não é o proprietário deste restaurante.']);
    }

    $mesa->id = $mesaId;
    $mesa->restaurante_id = $restauranteId;

    if ($mesa->deletar()) {
        Response::json(200, ['message' => 'Mesa deletada com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível deletar a mesa.']);
    }
}

else {
    error_log("LOG: Método ou endpoint não permitido para mesas.");
    Response::json(405, ['message' => 'Método ou endpoint não permitido para mesas.']);
}

error_log("LOG: --- Fim de mesas.php ---");