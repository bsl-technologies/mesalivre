<?php
// api/v1/reservas.php

error_log("LOG: --- Inicio de reservas.php ---");

$database = getDbConnection();
$reserva = new Reserva($database);
$usuario_model = new Usuario($database);
$restaurante_model = new Restaurante($database);

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$tokenPayload = AuthMiddleware::validateToken($authHeader);

$reservaId = $resourceId;

if ($method === 'POST' && $reservaId === null) {
    error_log("LOG: Método POST detectado para criar reserva.");

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    if (!AuthMiddleware::hasRole($tokenPayload, 'cliente') && !AuthMiddleware::hasRole($tokenPayload, 'admin') && !AuthMiddleware::hasRole($tokenPayload, 'restaurante')) {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para criar reservas.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->restaurante_id) || empty($data->mesa_id) || empty($data->horario) || empty($data->horario_fim) || empty($data->num_pessoas)) {
        Response::json(400, ['message' => 'Dados incompletos para criar a reserva. Campos obrigatórios: restaurante_id, mesa_id, horario, horario_fim, num_pessoas.']);
    }

    if (AuthMiddleware::hasRole($tokenPayload, 'cliente')) {
        $reserva->usuario_id = $tokenPayload['uid'];
    } else {
        $reserva->usuario_id = $data->usuario_id ?? $tokenPayload['uid'];

        if ($reserva->usuario_id !== $tokenPayload['uid']) {
             $usuario_model->id = $reserva->usuario_id;
             if (!$usuario_model->lerUm()) {
                 Response::json(400, ['message' => 'ID do usuário especificado para a reserva não encontrado.']);
             }
        }
    }

    $reserva->restaurante_id = $data->restaurante_id;
    $reserva->mesa_id = $data->mesa_id;
    $reserva->horario = $data->horario;
    $reserva->horario_fim = $data->horario_fim;
    $reserva->num_pessoas = $data->num_pessoas;
    $reserva->observacoes = $data->observacoes ?? null; 
    $reserva->status = $data->status ?? 'pendente'; 

    try {
        if ($reserva->criar()) {
            Response::json(201, ['message' => 'Reserva criada com sucesso!']);
        } else {
            Response::json(500, ['message' => 'Não foi possível criar a reserva devido a um erro desconhecido.']);
        }
    } catch (Exception $e) {
        error_log("API Error creating reservation: " . $e->getMessage() . " Code: " . $e->getCode());
        Response::json($e->getCode() ?: 500, ['message' => $e->getMessage()]);
    }
}

elseif ($method === 'GET' && $reservaId === null) {
    error_log("LOG: Método GET detectado para listar reservas.");

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    $status_filter = $_GET['status'] ?? null;
    $restaurante_id_filter = $_GET['restaurante_id'] ?? null;
    $filterType = 'all';
    $filterId = null;

    if (AuthMiddleware::hasRole($tokenPayload, 'cliente')) {
        $filterType = 'usuario';
        $filterId = $tokenPayload['uid'];
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'restaurante')) {
        if (empty($restaurante_id_filter)) {
            Response::json(400, ['message' => 'Para listar reservas como restaurante, o ID do restaurante deve ser fornecido (restaurante_id).']);
        }
        $restaurante_model->id = $restaurante_id_filter;
        if (!$restaurante_model->isOwner($tokenPayload['uid'])) {
            Response::json(403, ['message' => 'Você não é o proprietário deste restaurante.']);
        }
        $filterType = 'restaurante';
        $filterId = $restaurante_id_filter;
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'admin')) {
        if (!empty($_GET['usuario_id'])) {
            $filterType = 'usuario';
            $filterId = $_GET['usuario_id'];
        } elseif (!empty($restaurante_id_filter)) {
            $filterType = 'restaurante';
            $filterId = $restaurante_id_filter;
        }
    } else {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para listar reservas.']);
    }

    $reservas_data = $reserva->ler($filterType, $filterId, $status_filter, $offset, $limit);
    $total_registros = $reserva->contar($filterType, $filterId, $status_filter);

    Response::json(200, [
        'data' => $reservas_data,
        'pagina_atual' => $page,
        'total_paginas' => ceil($total_registros / $limit),
        'total_registros' => $total_registros
    ]);
}

elseif ($method === 'GET' && $reservaId !== null) {
    error_log("LOG: Método GET detectado para obter reserva: " . $reservaId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $reserva->id = $reservaId;

    if ($reserva->lerUma()) {
        if (AuthMiddleware::hasRole($tokenPayload, 'admin')) {
            Response::json(200, $reserva);
        } elseif (AuthMiddleware::hasRole($tokenPayload, 'cliente') && $reserva->isOwner($tokenPayload['uid'])) {
            Response::json(200, $reserva);
        } elseif (AuthMiddleware::hasRole($tokenPayload, 'restaurante')) {
            $restaurante_model->id = $reserva->restaurante_id;
            if ($restaurante_model->isOwner($tokenPayload['uid'])) { 
                Response::json(200, $reserva);
            } else {
                Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para ver esta reserva.']);
            }
        } else {
            Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para ver esta reserva.']);
        }
    } else {
        Response::json(404, ['message' => 'Reserva não encontrada.']);
    }
}
elseif ($method === 'PUT' && $reservaId !== null) {
    error_log("LOG: Método PUT detectado para atualizar reserva: " . $reservaId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->usuario_id) || empty($data->restaurante_id) || empty($data->mesa_id) || empty($data->horario) || empty($data->horario_fim) || empty($data->num_pessoas) || empty($data->status)) {
        Response::json(400, ['message' => 'Dados incompletos para atualização completa da reserva.']);
    }

    $reserva->id = $reservaId;
    if (!$reserva->lerUma()) {
        Response::json(404, ['message' => 'Reserva não encontrada para atualização.']);
    }

    if (AuthMiddleware::hasRole($tokenPayload, 'admin')) {
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'cliente') && $reserva->isOwner($tokenPayload['uid'])) {
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'restaurante')) {
        $restaurante_model->id = $reserva->restaurante_id;
        if (!$restaurante_model->isOwner($tokenPayload['uid'])) {
            Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar esta reserva.']);
        }
    } else {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar esta reserva.']);
    }

    $reserva->usuario_id = $data->usuario_id;
    $reserva->restaurante_id = $data->restaurante_id;
    $reserva->mesa_id = $data->mesa_id;
    $reserva->horario = $data->horario;
    $reserva->horario_fim = $data->horario_fim;
    $reserva->num_pessoas = $data->num_pessoas;
    $reserva->observacoes = $data->observacoes ?? null;
    $reserva->status = $data->status;

    try {
        if ($reserva->atualizar()) {
            Response::json(200, ['message' => 'Reserva atualizada com sucesso!']);
        }
    } catch (Exception $e) {
        error_log("API Error updating reservation: " . $e->getMessage() . " Code: " . $e->getCode());
        Response::json($e->getCode() ?: 500, ['message' => $e->getMessage()]);
    }
}
elseif ($method === 'PATCH' && $reservaId !== null) {
    error_log("LOG: Método PATCH detectado para atualizar reserva parcialmente: " . $reservaId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $data = json_decode(file_get_contents("php://input"), true); 

    if (empty($data)) {
        Response::json(400, ['message' => 'Nenhum dado fornecido para atualização parcial.']);
    }

    $reserva->id = $reservaId;
    if (!$reserva->lerUma()) {
        Response::json(404, ['message' => 'Reserva não encontrada para atualização parcial.']);
    }

    if (AuthMiddleware::hasRole($tokenPayload, 'admin')) {
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'cliente') && $reserva->isOwner($tokenPayload['uid'])) {
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'restaurante')) {
        $restaurante_model->id = $reserva->restaurante_id;
        if (!$restaurante_model->isOwner($tokenPayload['uid'])) {
            Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar esta reserva.']);
        }
    } else {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para atualizar esta reserva.']);
    }

    try {
        if ($reserva->atualizarParcialmente($data)) {
            Response::json(200, ['message' => 'Reserva atualizada parcialmente com sucesso!']);
        }
    } catch (Exception $e) {
        error_log("API Error patching reservation: " . $e->getMessage() . " Code: " . $e->getCode());
        Response::json($e->getCode() ?: 500, ['message' => $e->getMessage()]);
    }
}

elseif ($method === 'DELETE' && $reservaId !== null) {
    error_log("LOG: Método DELETE detectado para deletar reserva: " . $reservaId);

    if (!$tokenPayload) {
        Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
    }

    $reserva->id = $reservaId;
    if (!$reserva->lerUma()) {
        Response::json(404, ['message' => 'Reserva não encontrada para exclusão.']);
    }

    if (AuthMiddleware::hasRole($tokenPayload, 'admin')) {
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'cliente') && $reserva->isOwner($tokenPayload['uid'])) {
    } elseif (AuthMiddleware::hasRole($tokenPayload, 'restaurante')) {
        $restaurante_model->id = $reserva->restaurante_id;
        if (!$restaurante_model->isOwner($tokenPayload['uid'])) {
            Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para deletar esta reserva.']);
        }
    } else {
        Response::json(403, ['message' => 'Acesso negado. Você não tem permissão para deletar esta reserva.']);
    }

    try {
        if ($reserva->deletar()) {
            Response::json(200, ['message' => 'Reserva deletada com sucesso!']);
        }
    } catch (Exception $e) {
        error_log("API Error deleting reservation: " . $e->getMessage() . " Code: " . $e->getCode());
        Response::json($e->getCode() ?: 500, ['message' => $e->getMessage()]);
    }
}

error_log("LOG: --- Fim de reservas.php ---");
?>