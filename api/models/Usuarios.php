<?php
// api/models/Usuario.php

class Usuario
{
    private PDO $conn;
    private string $table_name = "usuarios";

    public string $id;
    public string $nome;
    public string $email;
    public string $senha;         // <--- Adicionado para evitar erro de propriedade dinâmica
    public string $senha_hash;
    public string $tipo_usuario;
    public string $data_criacao;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public function registrar(): bool
    {
        if ($this->emailExiste()) {
            return false;
        }

        $this->id = $this->generateUuid();

        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      id = :id,
                      nome = :nome,
                      email = :email,
                      senha = :senha,
                      tipo_usuario = :tipo_usuario";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->senha = password_hash($this->senha, PASSWORD_BCRYPT);
        $this->tipo_usuario = htmlspecialchars(strip_tags($this->tipo_usuario));

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':senha', $this->senha);
        $stmt->bindParam(':tipo_usuario', $this->tipo_usuario);

        if ($stmt->execute()) {
            return true;
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Erro ao registrar usuário: " . implode(" | ", $errorInfo));
            return false;
        }
    }

    public function emailExiste(): bool
    {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function buscarPorEmail(): bool
    {
        $query = "SELECT id, nome, email, senha, tipo_usuario FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->nome = $row['nome'];
            $this->email = $row['email'];
            $this->senha = $row['senha']; // Atribuição corrigida (senha hash)
            $this->tipo_usuario = $row['tipo_usuario'];
            return true;
        }
        return false;
    }

    public function buscarPorId(): bool
    {
        $query = "SELECT id, nome, email, tipo_usuario, data_criacao FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->nome = $row['nome'];
            $this->email = $row['email'];
            $this->tipo_usuario = $row['tipo_usuario'];
            $this->data_criacao = $row['data_criacao'];
            return true;
        }
        return false;
    }

    public function atualizar(): bool
    {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      nome = :nome,
                      email = :email
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Erro ao atualizar usuário: " . implode(" | ", $errorInfo));
            return false;
        }
    }

    public function listarPaginado(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT id, nome, email, tipo_usuario, data_criacao 
                  FROM " . $this->table_name . " 
                  ORDER BY data_criacao DESC 
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'records' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $this->contarUsuarios()
            ]
        ];
    }

    public function contarUsuarios(): int
    {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->query($query);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
