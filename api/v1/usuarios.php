<?php
// api/v1/usuarios.php

$database = getDbConnection();
$usuario = new Usuario($database);

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

$tokenPayload = AuthMiddleware::validateToken($authHeader);

if (!$tokenPayload) {
    Response::json(401, ['message' => 'Não autorizado. Token inválido ou ausente.']);
}

$authenticatedUserId = $tokenPayload['uid'];

if ($action === 'perfil') {
    if ($method === 'GET') {
        $usuario->id = $authenticatedUserId;

        if ($usuario->buscarPorId()) {
            Response::json(200, [
                'id' => $usuario->id,
                'nome' => $usuario->nome,
                'email' => $usuario->email,
                'tipo_usuario' => $usuario->tipo_usuario,
                'data_criacao' => $usuario->data_criacao
            ]);
        } else {
            Response::json(404, ['message' => 'Perfil de usuário não encontrado.']);
        }
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->nome) || empty($data->email)) {
            Response::json(400, ['message' => 'Nome e e-mail são obrigatórios para atualizar o perfil.']);
        }

        $usuario->id = $authenticatedUserId;
        $usuario->nome = $data->nome;
        $usuario->email = $data->email;

        if ($usuario->atualizar()) {
            Response::json(200, ['message' => 'Perfil atualizado com sucesso!']);
        } else {
            Response::json(500, ['message' => 'Não foi possível atualizar o perfil.']);
        }
    } else {
        Response::json(405, ['message' => 'Método não permitido para /usuarios/perfil.']);
    }
} elseif ($resourceId !== null) {
    Response::json(403, ['message' => 'Acesso negado ou recurso de usuário por ID não implementado.']);
} else {
    Response::json(404, ['message' => 'Endpoint de usuários não encontrado ou ação não especificada.']);
}
