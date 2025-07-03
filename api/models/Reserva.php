<?php
// api/models/Reserva.php
class Reserva {
    private $conn;
    private $table_name = "reservas";

    public $id;
    public $usuario_id;
    public $restaurante_id;
    public $mesa_id;
    public $horario;
    public $horario_fim;
    public $num_pessoas;
    public $observacoes;
    public $status;
    public $data_criacao;
    public $data_atualizacao;

    public $usuario_nome;
    public $restaurante_nome;
    public $mesa_numero;
    public $mesa_capacidade;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function getRestaurantConfig($restauranteId) {
        $query = "SELECT duracao_reserva_minutos, tolerancia_atraso_minutos FROM restaurantes WHERE id = :restaurante_id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restauranteId);
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config || empty($config['duracao_reserva_minutos'])) {
            return ['duracao_reserva_minutos' => 90, 'tolerancia_atraso_minutos' => 15];
        }
        return $config;
    }

    private function checkTableAvailability(
        $mesaId,
        $restauranteId,
        $numPessoas,
        DateTime $horarioInicio,
        DateTime $horarioFim,
        $reservaIdToExclude = null
    ) {
        $query_mesa = "SELECT id, capacidade FROM mesas WHERE id = :mesa_id AND restaurante_id = :restaurante_id LIMIT 0,1";
        $stmt_mesa = $this->conn->prepare($query_mesa);
        $stmt_mesa->bindParam(":mesa_id", $mesaId);
        $stmt_mesa->bindParam(":restaurante_id", $restauranteId);
        $stmt_mesa->execute();
        $mesa_info = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

        if (!$mesa_info) {
            throw new Exception("Mesa não encontrada para este restaurante.", 400);
        }
        if ($numPessoas > $mesa_info['capacidade']) {
            throw new Exception("Número de pessoas excede a capacidade da mesa (Máx: " . $mesa_info['capacidade'] . ").", 400);
        }

        if ($horarioFim <= $horarioInicio) {
            throw new Exception("O horário de término da reserva deve ser posterior ao horário de início.", 400);
        }

        $query_conflict = "SELECT r.id, r.horario, r.horario_fim
                           FROM " . $this->table_name . " r
                           WHERE r.mesa_id = :mesa_id
                           AND r.status IN ('pendente', 'confirmada')";

        if ($reservaIdToExclude) {
            $query_conflict .= " AND r.id != :reserva_id_to_exclude";
        }

        $query_conflict .= " AND (
                                   (:horario_inicio_nova < r.horario_fim) AND
                                   (:horario_fim_nova > r.horario)
                               )
                               LIMIT 0,1";

        $stmt_conflict = $this->conn->prepare($query_conflict);
        $stmt_conflict->bindParam(":mesa_id", $mesaId);
        $stmt_conflict->bindValue(":horario_inicio_nova", $horarioInicio->format('Y-m-d H:i:s'));
        $stmt_conflict->bindValue(":horario_fim_nova", $horarioFim->format('Y-m-d H:i:s'));

        if ($reservaIdToExclude) {
            $stmt_conflict->bindParam(":reserva_id_to_exclude", $reservaIdToExclude);
        }

        $stmt_conflict->execute();

        if ($stmt_conflict->rowCount() > 0) {
            $conflito_info = $stmt_conflict->fetch(PDO::FETCH_ASSOC);
            $horario_existente = new DateTime($conflito_info['horario']);
            $fim_existente = new DateTime($conflito_info['horario_fim']);

            throw new Exception(
                "Conflito de horário: A mesa já está reservada das " .
                $horario_existente->format('H:i') . " às " . $fim_existente->format('H:i') .
                " neste dia.", 409
            );
        }
    }

    public function criar() {
        if (empty($this->usuario_id) || empty($this->restaurante_id) || empty($this->mesa_id) || empty($this->horario) || empty($this->horario_fim) || empty($this->num_pessoas)) {
            throw new Exception("Dados obrigatórios para criar a reserva estão faltando (horário de fim incluído).", 400);
        }

        $horario_inicio_dt = new DateTime($this->horario);
        $horario_fim_dt = new DateTime($this->horario_fim);

        $this->conn->beginTransaction();
        try {
            $this->checkTableAvailability(
                $this->mesa_id,
                $this->restaurante_id,
                $this->num_pessoas,
                $horario_inicio_dt,
                $horario_fim_dt
            );

            $query = "INSERT INTO " . $this->table_name . "
                      SET
                          id = UUID(),
                          usuario_id = :usuario_id,
                          restaurante_id = :restaurante_id,
                          mesa_id = :mesa_id,
                          horario = :horario,
                          horario_fim = :horario_fim,
                          num_pessoas = :num_pessoas,
                          observacoes = :observacoes,
                          status = :status,
                          data_criacao = NOW(),
                          data_atualizacao = NOW()";

            $stmt = $this->conn->prepare($query);

            $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
            $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));
            $this->mesa_id = htmlspecialchars(strip_tags($this->mesa_id));
            $this->horario = $horario_inicio_dt->format('Y-m-d H:i:s');
            $this->horario_fim = $horario_fim_dt->format('Y-m-d H:i:s');
            $this->num_pessoas = htmlspecialchars(strip_tags($this->num_pessoas));
            $this->observacoes = $this->observacoes ? htmlspecialchars(strip_tags($this->observacoes)) : null;
            $this->status = htmlspecialchars(strip_tags($this->status));

            $stmt->bindParam(":usuario_id", $this->usuario_id);
            $stmt->bindParam(":restaurante_id", $this->restaurante_id);
            $stmt->bindParam(":mesa_id", $this->mesa_id);
            $stmt->bindParam(":horario", $this->horario);
            $stmt->bindParam(":horario_fim", $this->horario_fim);
            $stmt->bindParam(":num_pessoas", $this->num_pessoas);
            $stmt->bindParam(":observacoes", $this->observacoes);
            $stmt->bindParam(":status", $this->status);

            if ($stmt->execute()) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error (criar reserva): " . $errorInfo[2]);
                throw new Exception("Erro interno ao salvar a reserva: " . $errorInfo[2], 500);
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function lerUma() {
        $query = "SELECT
                      r.id, r.usuario_id, r.restaurante_id, r.mesa_id, r.horario, r.horario_fim, r.num_pessoas, r.observacoes, r.status, r.data_criacao, r.data_atualizacao,
                      u.nome as usuario_nome,
                      rest.nome as restaurante_nome,
                      m.numero as mesa_numero,
                      m.capacidade as mesa_capacidade
                  FROM
                      " . $this->table_name . " r
                  LEFT JOIN
                      usuarios u ON r.usuario_id = u.id
                  LEFT JOIN
                      restaurantes rest ON r.restaurante_id = rest.id
                  LEFT JOIN
                      mesas m ON r.mesa_id = m.id
                  WHERE
                      r.id = ?
                  LIMIT
                      0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->usuario_id = $row['usuario_id'];
            $this->restaurante_id = $row['restaurante_id'];
            $this->mesa_id = $row['mesa_id'];
            $this->horario = $row['horario'];
            $this->horario_fim = $row['horario_fim'];
            $this->num_pessoas = $row['num_pessoas'];
            $this->observacoes = $row['observacoes'];
            $this->status = $row['status'];
            $this->data_criacao = $row['data_criacao'];
            $this->data_atualizacao = $row['data_atualizacao'];
            $this->usuario_nome = $row['usuario_nome'];
            $this->restaurante_nome = $row['restaurante_nome'];
            $this->mesa_numero = $row['mesa_numero'];
            $this->mesa_capacidade = $row['mesa_capacidade'];
            return true;
        }
        return false;
    }

    public function atualizar() {
        if (empty($this->id) || empty($this->usuario_id) || empty($this->restaurante_id) || empty($this->mesa_id) || empty($this->horario) || empty($this->horario_fim) || empty($this->num_pessoas) || empty($this->status)) {
            throw new Exception("Dados obrigatórios para atualizar a reserva estão faltando (horário de fim incluído).", 400);
        }

        $horario_inicio_dt = new DateTime($this->horario);
        $horario_fim_dt = new DateTime($this->horario_fim);

        $this->conn->beginTransaction();
        try {
            $this->checkTableAvailability(
                $this->mesa_id,
                $this->restaurante_id,
                $this->num_pessoas,
                $horario_inicio_dt,
                $horario_fim_dt,
                $this->id
            );

            $query = "UPDATE " . $this->table_name . "
                      SET
                          usuario_id = :usuario_id,
                          restaurante_id = :restaurante_id,
                          mesa_id = :mesa_id,
                          horario = :horario,
                          horario_fim = :horario_fim,
                          num_pessoas = :num_pessoas,
                          observacoes = :observacoes,
                          status = :status,
                          data_atualizacao = NOW()
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
            $this->restaurante_id = htmlspecialchars(strip_tags($this->restaurante_id));
            $this->mesa_id = htmlspecialchars(strip_tags($this->mesa_id));
            $this->horario = $horario_inicio_dt->format('Y-m-d H:i:s');
            $this->horario_fim = $horario_fim_dt->format('Y-m-d H:i:s');
            $this->num_pessoas = htmlspecialchars(strip_tags($this->num_pessoas));
            $this->observacoes = $this->observacoes ? htmlspecialchars(strip_tags($this->observacoes)) : null;
            $this->status = htmlspecialchars(strip_tags($this->status));
            $this->id = htmlspecialchars(strip_tags($this->id));

            $stmt->bindParam(":usuario_id", $this->usuario_id);
            $stmt->bindParam(":restaurante_id", $this->restaurante_id);
            $stmt->bindParam(":mesa_id", $this->mesa_id);
            $stmt->bindParam(":horario", $this->horario);
            $stmt->bindParam(":horario_fim", $this->horario_fim);
            $stmt->bindParam(":num_pessoas", $this->num_pessoas);
            $stmt->bindParam(":observacoes", $this->observacoes);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":id", $this->id);

            if ($stmt->execute()) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error (atualizar reserva): " . $errorInfo[2]);
                throw new Exception("Erro interno ao atualizar a reserva: " . $errorInfo[2], 500);
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function atualizarParcialmente($data) {
        if (empty($data) || empty($this->id)) {
            throw new Exception("Nenhum dado fornecido para atualização parcial ou ID da reserva ausente.", 400);
        }

        if (!$this->lerUma()) {
            throw new Exception("Reserva não encontrada para atualização.", 404);
        }

        foreach ($data as $key => $value) {
            if (property_exists($this, $key) && $key !== 'id') {
                $this->$key = $value;
            }
        }

        $horario_inicio_dt = new DateTime($this->horario);
        $horario_fim_dt = new DateTime($this->horario_fim);

        $this->conn->beginTransaction();
        try {
            if (isset($data['horario']) || isset($data['horario_fim']) || isset($data['mesa_id']) || isset($data['num_pessoas'])) {
                $this->checkTableAvailability(
                    $this->mesa_id,
                    $this->restaurante_id,
                    $this->num_pessoas,
                    $horario_inicio_dt,
                    $horario_fim_dt,
                    $this->id
                );
            }

            $set_clause_parts = [];
            $params = [":id" => htmlspecialchars(strip_tags($this->id))];

            $updatable_fields = [
                'usuario_id', 'restaurante_id', 'mesa_id', 'horario', 'horario_fim',
                'num_pessoas', 'observacoes', 'status'
            ];

            foreach ($updatable_fields as $field) {
                if (isset($data[$field])) {
                    $set_clause_parts[] = "`{$field}` = :{$field}";

                    if ($field === 'observacoes' && ($this->$field === null || $this->$field === '')) {
                        $params[":{$field}"] = null;
                    } else {
                        $params[":{$field}"] = htmlspecialchars(strip_tags($this->$field));
                    }
                }
            }

            if (empty($set_clause_parts)) {
                $this->conn->rollBack();
                throw new Exception("Nenhum campo válido fornecido para atualização.", 400);
            }

            $set_clause = implode(", ", $set_clause_parts) . ", `data_atualizacao` = NOW()";
            $query = "UPDATE " . $this->table_name . " SET " . $set_clause . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);

            foreach ($params as $param_key => &$param_value) {
                $pdo_type = PDO::PARAM_STR;
                if ($param_value === null) {
                    $pdo_type = PDO::PARAM_NULL;
                } elseif (is_int($param_value)) {
                    $pdo_type = PDO::PARAM_INT;
                }
                $stmt->bindParam($param_key, $param_value, $pdo_type);
            }

            if ($stmt->execute()) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error (atualizar parcialmente reserva): " . $errorInfo[2]);
                throw new Exception("Erro interno ao atualizar a reserva parcialmente. Detalhes: " . $errorInfo[2], 500);
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function deletar() {
        if (empty($this->id)) {
            throw new Exception("ID da reserva não fornecido para exclusão.", 400);
        }

        $this->conn->beginTransaction();
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);

            $this->id = htmlspecialchars(strip_tags($this->id));
            $stmt->bindParam(":id", $this->id);

            if ($stmt->execute()) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error (deletar reserva): " . $errorInfo[2]);
                throw new Exception("Não foi possível deletar a reserva: " . $errorInfo[2], 500);
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function ler($filterType = 'all', $filterId = null, $statusFilter = null, $offset = 0, $limit = 10) {
        $query = "SELECT
                      r.id, r.usuario_id, r.restaurante_id, r.mesa_id, r.horario, r.horario_fim, r.num_pessoas, r.observacoes, r.status, r.data_criacao, r.data_atualizacao,
                      u.nome as usuario_nome,
                      rest.nome as restaurante_nome,
                      m.numero as mesa_numero,
                      m.capacidade as mesa_capacidade
                  FROM
                      " . $this->table_name . " r
                  LEFT JOIN
                      usuarios u ON r.usuario_id = u.id
                  LEFT JOIN
                      restaurantes rest ON r.restaurante_id = rest.id
                  LEFT JOIN
                      mesas m ON r.mesa_id = m.id";

        $conditions = [];
        $params = [];

        if ($filterType === 'usuario' && $filterId !== null) {
            $conditions[] = "r.usuario_id = :filterId";
            $params[":filterId"] = $filterId;
        } elseif ($filterType === 'restaurante' && $filterId !== null) {
            $conditions[] = "r.restaurante_id = :filterId";
            $params[":filterId"] = $filterId;
        }

        if ($statusFilter !== null) {
            $conditions[] = "r.status = :statusFilter";
            $params[":statusFilter"] = $statusFilter;
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY r.horario ASC LIMIT :offset, :limit";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar($filterType = 'all', $filterId = null, $statusFilter = null) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " r";

        $conditions = [];
        $params = [];

        if ($filterType === 'usuario' && $filterId !== null) {
            $conditions[] = "r.usuario_id = :filterId";
            $params[":filterId"] = $filterId;
        } elseif ($filterType === 'restaurante' && $filterId !== null) {
            $conditions[] = "r.restaurante_id = :filterId";
            $params[":filterId"] = $filterId;
        }

        if ($statusFilter !== null) {
            $conditions[] = "r.status = :statusFilter";
            $params[":statusFilter"] = $statusFilter;
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function isOwner($userId) {
        return $this->usuario_id === $userId;
    }

    public function getOccupiedTimeSlots($mesaId, $restauranteId, $data) {
        $query = "SELECT
                      horario,
                      horario_fim
                  FROM
                      " . $this->table_name . "
                  WHERE
                      mesa_id = :mesa_id AND
                      restaurante_id = :restaurante_id AND
                      DATE(horario) = :data AND
                      status IN ('pendente', 'confirmada')
                  ORDER BY horario ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":mesa_id", $mesaId);
        $stmt->bindParam(":restaurante_id", $restauranteId);
        $stmt->bindParam(":data", $data);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}