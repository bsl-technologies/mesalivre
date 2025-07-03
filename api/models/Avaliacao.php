<?php

class Avaliacao {
    private PDO $conn;
    private string $table_name = "avaliacoes";

    public string $id;
    public string $restaurante_id;
    public string $usuario_id;
    public int $nota; // Ex: 1 a 5
    public ?string $comentario;
    public string $data_criacao;
    public string $data_atualizacao;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function criar(): bool {
        // Validação: um usuário pode fazer apenas uma avaliação por restaurante
        $query_existente = "SELECT COUNT(*) FROM " . $this->table_name . "
                            WHERE restaurante_id = :restaurante_id AND usuario_id = :usuario_id";
        $stmt_existente = $this->conn->prepare($query_existente);
        $stmt_existente->bindParam(":restaurante_id", $this->restaurante_id);
        $stmt_existente->bindParam(":usuario_id", $this->usuario_id);
        $stmt_existente->execute();

        if ($stmt_existente->fetchColumn() > 0) {
            error_log("ERRO: Usuário já avaliou este restaurante.");
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      id = UUID(),
                      restaurante_id = :restaurante_id,
                      usuario_id = :usuario_id,
                      nota = :nota,
                      comentario = :comentario";

        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
        $this->nota = htmlspecialchars(strip_tags($this->nota));
        $this->comentario = htmlspecialchars(strip_tags($this->comentario));

        // Vincula os valores
        $stmt->bindParam(":restaurante_id", $this->restaurante_id);
        $stmt->bindParam(":usuario_id", $this->usuario_id);
        $stmt->bindParam(":nota", $this->nota);
        $stmt->bindParam(":comentario", $this->comentario);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Avaliacao->criar(): " . $e->getMessage());
            return false;
        }
    }

    public function ler(string $restauranteId, int $offset = 0, int $limit = 10): array {
        $query = "SELECT a.id, a.restaurante_id, a.usuario_id, a.nota, a.comentario, a.data_criacao, a.data_atualizacao,
                         u.nome as usuario_nome
                  FROM " . $this->table_name . " a
                  JOIN usuarios u ON a.usuario_id = u.id
                  WHERE a.restaurante_id = :restaurante_id
                  ORDER BY a.data_criacao DESC LIMIT :offset, :limit";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":restaurante_id", $restauranteId);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);

        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ERRO PDO em Avaliacao->ler(): " . $e->getMessage() . " --- SQL: " . $query);
            return [];
        }
    }

    public function contar(string $restauranteId): int {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE restaurante_id = :restaurante_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restauranteId);

        try {
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Avaliacao->contar(): " . $e->getMessage() . " --- SQL: " . $query);
            return 0;
        }
    }

    public function lerUma(): bool {
        $query = "SELECT a.id, a.restaurante_id, a.usuario_id, a.nota, a.comentario, a.data_criacao, a.data_atualizacao,
                         u.nome as usuario_nome
                  FROM " . $this->table_name . " a
                  JOIN usuarios u ON a.usuario_id = u.id
                  WHERE a.id = :id AND a.restaurante_id = :restaurante_id LIMIT 0,1";

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
                $this->restaurante_id = $row['restaurante_id'];
                $this->usuario_id = $row['usuario_id'];
                $this->nota = $row['nota'];
                $this->comentario = $row['comentario'];
                $this->data_criacao = $row['data_criacao'];
                $this->data_atualizacao = $row['data_atualizacao'];
                $this->usuario_nome = $row['usuario_nome'] ?? null;
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("ERRO PDO em Avaliacao->lerUma(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function atualizar(): bool {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      nota = :nota,
                      comentario = :comentario
                  WHERE id = :id AND restaurante_id = :restaurante_id AND usuario_id = :usuario_id";

        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->nota = htmlspecialchars(strip_tags($this->nota));
        $this->comentario = htmlspecialchars(strip_tags($this->comentario));
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));

        // Vincula os valores
        $stmt->bindParam(":nota", $this->nota);
        $stmt->bindParam(":comentario", $this->comentario);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":restaurante_id", $this->restaurante_id);
        $stmt->bindParam(":usuario_id", $this->usuario_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Avaliacao->atualizar(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function atualizarParcialmente(array $updates): bool {
        $setParts = [];
        $params = [
            ':id' => $this->id,
            ':restaurante_id' => $this->restaurante_id,
            ':usuario_id' => $this->usuario_id
        ];

        foreach ($updates as $key => $value) {
            if (in_array($key, ['id', 'restaurante_id', 'usuario_id', 'data_criacao', 'data_atualizacao'])) {
                continue; // Não permite alterar IDs ou datas
            }
            $setParts[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = htmlspecialchars(strip_tags($value));
        }

        if (empty($setParts)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $setParts) . " WHERE id = :id AND restaurante_id = :restaurante_id AND usuario_id = :usuario_id";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Avaliacao->atualizarParcialmente(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    public function deletar(): bool {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND restaurante_id = :restaurante_id AND usuario_id = :usuario_id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":restaurante_id", $this->restaurante_id);
        $stmt->bindParam(":usuario_id", $this->usuario_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ERRO PDO em Avaliacao->deletar(): " . $e->getMessage() . " --- SQL: " . $query);
            return false;
        }
    }

    // Verifica se o usuário autenticado é o criador da avaliação
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