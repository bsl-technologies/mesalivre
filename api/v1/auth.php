<?php
// api/v1/auth.php

$database = getDbConnection();
$usuario = new Usuario($database);

if (empty($action)) {
    Response::json(400, ['message' => 'Ação de autenticação não especificada (ex: /auth/registrar, /auth/login).']);
}

switch ($action) {
    case 'registrar':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents("php://input"));
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::json(400, ['message' => 'JSON inválido no corpo da requisição.']);
            }

            if (empty($data->nome) || empty($data->email) || empty($data->senha) || empty($data->tipo_usuario)) {
                Response::json(400, ['message' => 'Dados incompletos para registro.']);
            }

            $usuario->nome = $data->nome;
            $usuario->email = $data->email;
            $usuario->senha = $data->senha;
            $usuario->tipo_usuario = $data->tipo_usuario;

            if ($usuario->registrar()) {
                $token = AuthMiddleware::generateToken($usuario->id, $usuario->tipo_usuario);
                Response::json(201, [
                    'message' => 'Usuário registrado com sucesso.',
                    'token' => 'Bearer ' . $token,
                    'usuario' => [
                        'id' => $usuario->id,
                        'nome' => $usuario->nome,
                        'email' => $usuario->email,
                        'tipo_usuario' => $usuario->tipo_usuario
                    ]
                ]);
            } else {
                if ($usuario->emailExiste()) {
                    Response::json(409, ['message' => 'Este e-mail já está em uso.']);
                }
                Response::json(500, ['message' => 'Não foi possível registrar o usuário.']);
            }
        } else {
            Response::json(405, ['message' => 'Método não permitido para /registrar.']);
        }
        break;

    case 'login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents("php://input"));
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::json(400, ['message' => 'JSON inválido no corpo da requisição.']);
            }

            if (empty($data->email) || empty($data->senha)) {
                Response::json(400, ['message' => 'E-mail e senha são obrigatórios.']);
            }

            $usuario->email = $data->email;

            if ($usuario->buscarPorEmail()) {
                if (password_verify($data->senha, $usuario->senha)) {
                    $token = AuthMiddleware::generateToken($usuario->id, $usuario->tipo_usuario);
                    Response::json(200, [
                        'message' => 'Login realizado com sucesso.',
                        'token' => 'Bearer ' . $token,
                        'usuario' => [
                            'id' => $usuario->id,
                            'nome' => $usuario->nome,
                            'email' => $usuario->email,
                            'tipo_usuario' => $usuario->tipo_usuario
                        ]
                    ]);
                } else {
                    Response::json(401, ['message' => 'Credenciais inválidas.']);
                }
            } else {
                Response::json(401, ['message' => 'Credenciais inválidas.']);
            }
        } else {
            Response::json(405, ['message' => 'Método não permitido para /login.']);
        }
        break;

    case 'resetar-senha':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents("php://input"));
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::json(400, ['message' => 'JSON inválido no corpo da requisição.']);
            }

            if (empty($data->email)) {
                Response::json(400, ['message' => 'E-mail é obrigatório para resetar a senha.']);
            }

            Response::json(200, ['message' => 'Se o e-mail estiver cadastrado, um link de redefinição de senha foi enviado.']);
        } else {
            Response::json(405, ['message' => 'Método não permitido para /resetar-senha.']);
        }
        break;

    case 'confirmar-reset-senha':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents("php://input"));
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::json(400, ['message' => 'JSON inválido no corpo da requisição.']);
            }

            if (empty($data->email) || empty($data->token_reset) || empty($data->nova_senha)) {
                Response::json(400, ['message' => 'Dados incompletos para confirmar redefinição de senha.']);
            }

            Response::json(200, ['message' => 'Senha redefinida com sucesso.']);
        } else {
            Response::json(405, ['message' => 'Método não permitido para /confirmar-reset-senha.']);
        }
        break;

    default:
        Response::json(404, ['message' => 'Ação de autenticação não reconhecida.']);
        break;
}
