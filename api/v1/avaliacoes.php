<?php
// api/v1/avaliacoes.php

error_log("LOG: --- Inicio de avaliacoes.php ---");

$database = getDbConnection();
$avaliacao = new Avaliacao($database);

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$tokenPayload = AuthMiddleware::validateToken($authHeader);

$restauranteId = $resourceId;
$avaliacaoId = $secondaryResourceId;


if ($method === 'POST' && $restauranteId !== null && $avaliacaoId === null) {
    error_log("LOG: Método POST detectado para criar avaliação para o restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'cliente')) {
        Response::json(403, ['message' => 'Acesso negado. Apenas clientes podem criar avaliações.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->nota)) {
        Response::json(400, ['message' => 'Dados incompletos para criar a avaliação. Campo obrigatório: nota (1-5).']);
    }

    if ($data->nota < 1 || $data->nota > 5) {
        Response::json(400, ['message' => 'A nota deve ser um valor entre 1 e 5.']);
    }

    $avaliacao->restaurante_id = $restauranteId;
    $avaliacao->usuario_id = $tokenPayload['uid'];
    $avaliacao->nota = $data->nota;
    $avaliacao->comentario = $data->comentario ?? null; 

    if ($avaliacao->criar()) {
        Response::json(201, ['message' => 'Avaliação criada com sucesso!']);
    } else {
        $temp_avaliacao_check = new Avaliacao($database);
        $temp_avaliacao_check->restaurante_id = $restauranteId;
        $temp_avaliacao_check->usuario_id = $tokenPayload['uid'];

        $stmt_check_duplicate = $database->prepare("SELECT COUNT(*) FROM avaliacoes WHERE restaurante_id = :rid AND usuario_id = :uid");
        $stmt_check_duplicate->bindParam(':rid', $restauranteId);
        $stmt_check_duplicate->bindParam(':uid', $tokenPayload['uid']);
        $stmt_check_duplicate->execute();
        if ($stmt_check_duplicate->fetchColumn() > 0) {
            Response::json(409, ['message' => 'Você já enviou uma avaliação para este restaurante.']);
        } else {
            Response::json(500, ['message' => 'Não foi possível criar a avaliação. Um erro interno ocorreu.']);
        }
    }
}

elseif ($method === 'GET' && $restauranteId !== null && $avaliacaoId === null) {
    error_log("LOG: Método GET detectado para listar avaliações do restaurante: " . $restauranteId);

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    $avaliacoes_data = $avaliacao->ler($restauranteId, $offset, $limit);
    $total_registros = $avaliacao->contar($restauranteId);

    Response::json(200, [
        'data' => $avaliacoes_data,
        'pagina_atual' => $page,
        'total_paginas' => ceil($total_registros / $limit),
        'total_registros' => $total_registros
    ]);
}

elseif ($method === 'GET' && $restauranteId !== null && $avaliacaoId !== null) {
    error_log("LOG: Método GET detectado para obter avaliação: " . $avaliacaoId . " do restaurante: " . $restauranteId);

    $avaliacao->id = $avaliacaoId;
    $avaliacao->restaurante_id = $restauranteId;

    if ($avaliacao->lerUma()) {
        $avaliacao_arr = [
            "id" => $avaliacao->id,
            "restaurante_id" => $avaliacao->restaurante_id,
            "usuario_id" => $avaliacao->usuario_id,
            "usuario_nome" => $avaliacao->usuario_nome ?? null,
            "nota" => $avaliacao->nota,
            "comentario" => $avaliacao->comentario,
            "data_criacao" => $avaliacao->data_criacao,
            "data_atualizacao" => $avaliacao->data_atualizacao
        ];
        Response::json(200, $avaliacao_arr);
    } else {
        Response::json(404, ['message' => 'Avaliação não encontrada para este restaurante.']);
    }
}

elseif ($method === 'PUT' && $restauranteId !== null && $avaliacaoId !== null) {
    error_log("LOG: Método PUT detectado para atualizar avaliação: " . $avaliacaoId . " do restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $avaliacao->id = $avaliacaoId;
    $avaliacao->restaurante_id = $restauranteId;

    if (!$avaliacao->lerUma()) {
        Response::json(404, ['message' => 'Avaliação não encontrada para este restaurante.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$avaliacao->isOwner($tokenPayload['uid'])) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar esta avaliação.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->nota)) {
        Response::json(400, ['message' => 'Dados incompletos para atualizar a avaliação. Campo obrigatório: nota (1-5).']);
    }
    if ($data->nota < 1 || $data->nota > 5) {
        Response::json(400, ['message' => 'A nota deve ser um valor entre 1 e 5.']);
    }

    $avaliacao->usuario_id = $tokenPayload['uid'];
    $avaliacao->nota = $data->nota;
    $avaliacao->comentario = $data->comentario ?? null;

    if ($avaliacao->atualizar()) {
        Response::json(200, ['message' => 'Avaliação atualizada com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível atualizar a avaliação.']);
    }
}

elseif ($method === 'PATCH' && $restauranteId !== null && $avaliacaoId !== null) {
    error_log("LOG: Método PATCH detectado para atualizar avaliação: " . $avaliacaoId . " do restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $avaliacao->id = $avaliacaoId;
    $avaliacao->restaurante_id = $restauranteId;

    if (!$avaliacao->lerUma()) {
        Response::json(404, ['message' => 'Avaliação não encontrada para este restaurante.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$avaliacao->isOwner($tokenPayload['uid'])) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar esta avaliação.']);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data)) {
        Response::json(400, ['message' => 'Nenhum dado fornecido para atualização parcial da avaliação.']);
    }

    $avaliacao->usuario_id = $tokenPayload['uid'];

    if (isset($data['nota'])) {
        if ($data['nota'] < 1 || $data['nota'] > 5) {
            Response::json(400, ['message' => 'A nota deve ser um valor entre 1 e 5.']);
        }
    }

    unset($data['id']);
    unset($data['restaurante_id']);
    unset($data['usuario_id']);
    unset($data['data_criacao']);
    unset($data['data_atualizacao']);

    if ($avaliacao->atualizarParcialmente($data)) {
        Response::json(200, ['message' => 'Avaliação atualizada parcialmente com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível atualizar a avaliação parcialmente.']);
    }
}

elseif ($method === 'DELETE' && $restauranteId !== null && $avaliacaoId !== null) {
    error_log("LOG: Método DELETE detectado para deletar avaliação: " . $avaliacaoId . " do restaurante: " . $restauranteId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $avaliacao->id = $avaliacaoId;
    $avaliacao->restaurante_id = $restauranteId;

    if (!$avaliacao->lerUma()) {
        Response::json(404, ['message' => 'Avaliação não encontrada para este restaurante.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'admin') && !$avaliacao->isOwner($tokenPayload['uid'])) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para deletar esta avaliação.']);
    }

    $avaliacao->usuario_id = $tokenPayload['uid'];

    if ($avaliacao->deletar()) {
        Response::json(200, ['message' => 'Avaliação deletada com sucesso!']);
    } else {
        Response::json(500, ['message' => 'Não foi possível deletar a avaliação.']);
    }
}

else {
    error_log("LOG: Método ou endpoint não permitido para avaliações.");
    Response::json(405, ['message' => 'Método ou endpoint não permitido para avaliações.']);
}

error_log("LOG: --- Fim de avaliacoes.php ---");