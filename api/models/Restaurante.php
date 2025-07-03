<?php
// api/models/Restaurante.php
class Restaurante {
    private PDO $conn;
    private string $table_name = "restaurantes";

    public string $id;
    public string $nome;
    public string $endereco;
    public string $cidade;
    public ?string $telefone;
    public string $culinaria;
    public string $horarios_funcionamento;
    public ?string $descricao;
    public ?string $fotos;
    public string $usuario_id;
    public string $data_criacao;
    public string $data_atualizacao;
    public int $excluido = 0;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function criar(): bool {
        if ($this->nomeExiste()) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      id = UUID(),
                      nome = :nome,
                      endereco = :endereco,
                      cidade = :cidade,
                      telefone = :telefone,
                      culinaria = :culinaria,
                      horarios_funcionamento = :horarios_funcionamento,
                      descricao = :descricao,
                      fotos = :fotos,
                      usuario_id = :usuario_id,
                      excluido = :excluido";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->endereco = htmlspecialchars(strip_tags($this->endereco));
        $this->cidade = htmlspecialchars(strip_tags($this->cidade));
        $this->culinaria = htmlspecialchars(strip_tags($this->culinaria));
        $this->horarios_funcionamento = htmlspecialchars(strip_tags($this->horarios_funcionamento));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));

        $this->telefone = isset($this->telefone) ? htmlspecialchars(strip_tags($this->telefone)) : null;
        $this->descricao = isset($this->descricao) ? htmlspecialchars(strip_tags($this->descricao)) : null;

        $fotos_para_db = $this->fotos;

        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":endereco", $this->endereco);
        $stmt->bindParam(":cidade", $this->cidade);
        $stmt->bindParam(":telefone", $this->telefone);
        $stmt->bindParam(":culinaria", $this->culinaria);
        $stmt->bindParam(":horarios_funcionamento", $this->horarios_funcionamento);
        $stmt->bindParam(":descricao", $this->descricao);
        $stmt->bindParam(":fotos", $fotos_para_db);
        $stmt->bindParam(":usuario_id", $this->usuario_id);
        $stmt->bindParam(":excluido", $this->excluido, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->criar(): " . $e->getMessage());
            return false;
        }
    }

    public function ler(int $offset = 0, int $limit = 10, string $busca = '', string $culinaria = '', string $cidade = ''): array {
        $query = "SELECT id, nome, endereco, telefone, culinaria, horarios_funcionamento, descricao, fotos, usuario_id, data_criacao, data_atualizacao
                  FROM " . $this->table_name;
        $conditions = ["excluido = 0"];
        $params = [];

        if (!empty($busca)) {
            $conditions[] = "(nome LIKE :busca OR descricao LIKE :busca)";
            $params[':busca'] = '%' . $busca . '%';
        }
        if (!empty($culinaria)) {
            $conditions[] = "culinaria = :culinaria";
            $params[':culinaria'] = $culinaria;
        }
        if (!empty($cidade)) {
            $conditions[] = "endereco LIKE :cidade";
            $params[':cidade'] = '%' . $cidade . '%';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY nome ASC LIMIT :offset, :limit";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);

        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->ler(): " . $e->getMessage() . " --- SQL: " . $query);
            return [];
        }
    }

    public function contar(string $busca = '', string $culinaria = '', string $cidade = ''): int {
        $query = "SELECT COUNT(*) FROM " . $this->table_name;
        $conditions = ["excluido = 0"];
        $params = [];

        if (!empty($busca)) {
            $conditions[] = "(nome LIKE :busca OR descricao LIKE :busca)";
            $params[':busca'] = '%' . $busca . '%';
        }
        if (!empty($culinaria)) {
            $conditions[] = "culinaria = :culinaria";
            $params[':culinaria'] = $culinaria;
        }
        if (!empty($cidade)) {
            $conditions[] = "endereco LIKE :cidade";
            $params[':cidade'] = '%' . $cidade . '%';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        try {
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->contar(): " . $e->getMessage() . " --- SQL: " . $query);
            return 0;
        }
    }

    public function lerUm(): bool {
        $query = "SELECT id, nome, endereco, cidade, telefone, culinaria, horarios_funcionamento, descricao, fotos, usuario_id, data_criacao, data_atualizacao, excluido
                  FROM " . $this->table_name . "
                  WHERE id = ? AND excluido = 0 LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        try {
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                $this->id = $row['id'] ?? '';
                $this->nome = $row['nome'] ?? '';
                $this->endereco = $row['endereco'] ?? '';
                $this->cidade = $row['cidade'] ?? '';
                $this->telefone = $row['telefone'] ?? null;
                $this->culinaria = $row['culinaria'] ?? '';
                $this->horarios_funcionamento = $row['horarios_funcionamento'] ?? '';
                $this->descricao = $row['descricao'] ?? null;
                $this->fotos = $row['fotos'] ?? null;
                $this->usuario_id = $row['usuario_id'] ?? '';
                $this->data_criacao = $row['data_criacao'] ?? '';
                $this->data_atualizacao = $row['data_atualizacao'] ?? '';
                $this->excluido = $row['excluido'] ?? 0;

                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->lerUm(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function atualizar(): bool {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      nome = :nome,
                      endereco = :endereco,
                      cidade = :cidade,
                      telefone = :telefone,
                      culinaria = :culinaria,
                      horarios_funcionamento = :horarios_funcionamento,
                      descricao = :descricao,
                      fotos = :fotos
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->endereco = htmlspecialchars(strip_tags($this->endereco));
        $this->cidade = htmlspecialchars(strip_tags($this->cidade));
        $this->culinaria = htmlspecialchars(strip_tags($this->culinaria));
        $this->horarios_funcionamento = htmlspecialchars(strip_tags($this->horarios_funcionamento));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $this->telefone = isset($this->telefone) ? htmlspecialchars(strip_tags($this->telefone)) : null;
        $this->descricao = isset($this->descricao) ? htmlspecialchars(strip_tags($this->descricao)) : null;

        $fotos_para_db = $this->fotos;

        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":endereco", $this->endereco);
        $stmt->bindParam(":cidade", $this->cidade);
        $stmt->bindParam(":telefone", $this->telefone);
        $stmt->bindParam(":culinaria", $this->culinaria);
        $stmt->bindParam(":horarios_funcionamento", $this->horarios_funcionamento);
        $stmt->bindParam(":descricao", $this->descricao);
        $stmt->bindParam(":fotos", $fotos_para_db);
        $stmt->bindParam(":id", $this->id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->atualizar(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function atualizarParcialmente(array $updates): bool {
        $setParts = [];
        $params = [':id' => $this->id];

        foreach ($updates as $key => $value) {
            if (in_array($key, ['id', 'usuario_id', 'data_criacao', 'data_atualizacao'])) {
                continue;
            }

            $sanitizedValue = ($key === 'fotos') ? $value : htmlspecialchars(strip_tags($value));

            $setParts[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = $sanitizedValue;
        }

        if (empty($setParts)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $setParts) . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->atualizarParcialmente(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function deletar(): bool {
        $query = "UPDATE " . $this->table_name . " SET excluido = 1 WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->deletar(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function restaurar(): bool {
        $query = "UPDATE " . $this->table_name . " SET excluido = 0 WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Restaurante->restaurar(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function nomeExiste(): bool {
        $query = "SELECT id FROM " . $this->table_name . " WHERE nome = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $stmt->bindParam(1, $this->nome);

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function isOwner(string $userId): bool {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE id = :id AND usuario_id = :usuario_id LIMIT 1";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $userId = htmlspecialchars(strip_tags($userId));

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":usuario_id", $userId);

        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }
}
