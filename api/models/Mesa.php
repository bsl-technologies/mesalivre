<?php
// api/models/Mesa.php

class Mesa {
    private PDO $conn;
    private string $table_name = "mesas";

    public string $id;
    public int $numero;
    public int $capacidade;
    public string $restaurante_id;
    public ?string $status; // Ex: "disponivel", "ocupada", "reservada"
    public string $data_criacao;
    public string $data_atualizacao;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function criar(): bool {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      id = UUID(),
                      numero = :numero,
                      capacidade = :capacidade,
                      restaurante_id = :restaurante_id,
                      status = :status";

        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->capacidade = htmlspecialchars(strip_tags($this->capacidade));
        $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));
        $this->status = htmlspecialchars(strip_tags($this->status));

        // Vincula os valores
        $stmt->bindParam(":numero", $this->numero);
        $stmt->bindParam(":capacidade", $this->capacidade);
        $stmt->bindParam(":restaurante_id", $this->restaurante_id);
        $stmt->bindParam(":status", $this->status);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Mesa->criar(): " . $e->getMessage());
            return false;
        }
    }

    public function ler(string $restauranteId, int $offset = 0, int $limit = 10, ?string $status = null): array {
        $query = "SELECT id, numero, capacidade, restaurante_id, status, data_criacao, data_atualizacao
                  FROM " . $this->table_name . "
                  WHERE restaurante_id = :restaurante_id";
        $params = [':restaurante_id' => $restauranteId];

        if ($status !== null && !empty($status)) {
            $query .= " AND status = :status";
            $params[':status'] = htmlspecialchars(strip_tags($status));
        }

        $query .= " ORDER BY numero ASC LIMIT :offset, :limit";

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
            error_log("ERRO PDO em Mesa->ler(): " . $e->getMessage() . " --- SQL: " . $query);
            return [];
        }
    }

    public function contar(string $restauranteId, ?string $status = null): int {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE restaurante_id = :restaurante_id";
        $params = [':restaurante_id' => $restauranteId];

        if ($status !== null && !empty($status)) {
            $query .= " AND status = :status";
            $params[':status'] = htmlspecialchars(strip_tags($status));
        }

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        try {
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Mesa->contar(): " . $e->getMessage() . " --- SQL: " . $query);
            return 0;
        }
    }

    public function lerUma(): bool {
        $query = "SELECT id, numero, capacidade, restaurante_id, status, data_criacao, data_atualizacao
                  FROM " . $this->table_name . "
                  WHERE id = :id AND restaurante_id = :restaurante_id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":restaurante_id", $this->restaurante_id);

        try {
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $row['id'];
                $this->numero = $row['numero'];
                $this->capacidade = $row['capacidade'];
                $this->restaurante_id = $row['restaurante_id'];
                $this->status = $row['status'];
                $this->data_criacao = $row['data_criacao'];
                $this->data_atualizacao = $row['data_atualizacao'];
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("ERRO PDO em Mesa->lerUma(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function atualizar(): bool {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      numero = :numero,
                      capacidade = :capacidade,
                      status = :status
                  WHERE id = :id AND restaurante_id = :restaurante_id";

        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->capacidade = htmlspecialchars(strip_tags($this->capacidade));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));

        // Vincula os valores
        $stmt->bindParam(":numero", $this->numero);
        $stmt->bindParam(":capacidade", $this->capacidade);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":restaurante_id", $this->restaurante_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Mesa->atualizar(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function atualizarParcialmente(array $updates): bool {
        $setParts = [];
        $params = [
            ':id' => $this->id,
            ':restaurante_id' => $this->restaurante_id
        ];

        foreach ($updates as $key => $value) {
            if (in_array($key, ['id', 'restaurante_id', 'data_criacao', 'data_atualizacao'])) {
                continue;
            }
            $setParts[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = htmlspecialchars(strip_tags($value));
        }

        if (empty($setParts)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $setParts) . " WHERE id = :id AND restaurante_id = :restaurante_id";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Mesa->atualizarParcialmente(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function deletar(): bool {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND restaurante_id = :restaurante_id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":restaurante_id", $this->restaurante_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Mesa->deletar(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    // Verifica se o usuário autenticado é o proprietário do restaurante ao qual a mesa pertence.
    // Isso é crucial para autorização.
    public function isOwner(string $userId, string $restauranteId): bool {
        // Primeiro, obtenha o ID do usuário que criou o restaurante.
        $query = "SELECT usuario_id FROM restaurantes WHERE id = :restaurante_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':restaurante_id', $restauranteId);

        try {
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['usuario_id'] === $userId;
            }
            return false;
        } catch (PDOException $e) {
            error_log("ERRO PDO em Mesa->isOwner (verificação de restaurante): " . $e->getMessage());
            return false;
        }
    }
}