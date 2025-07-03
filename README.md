# MesaLivre API

API para gerenciamento de restaurantes, mesas, reservas e avaliações.

## 🚀 Instalação e Configuração

Para configurar e rodar a API Mesa Livre, siga os passos abaixo:

### Pré-requisitos
1. PHP 8+
2. MySQL
3. phpmyadmin
4. Postman


### Configuração da API

1.  **Clonar o Repositório:**
    ```bash
    git clone git@github.com:bsl-technologies/mesalivre.git
    cd mesalivre
    ```
2. **Execute a API:**
    ```bash
    php -S localhost:8000
    ```

### Configuração do Banco de Dados

1.  **Importe o Esquema:** Usando o phpmyadmin, importe o conteúdo do arquivo `mesalivre.sql` para o banco de dados `mesalivre` que você acabou de criar. Isso criará todas as tabelas e índices necessários.

### Postman

1. **Importe o arquivo mesalivre.yaml**
2. **Ajuste o {{baseUrl}} conforme o seu endereco de IP do seu servidor**

## 🚀 Endpoints da API

A API do Mesa Livre é organizada em torno de recursos RESTful, utilizando os seguintes grupos de endpoints:

### Autenticação (Auth)
* **Registrar novo usuário**: `POST /auth?action=registrar`
    * Registra um novo usuário com nome, email, senha e tipo de usuário (`admin`, `restaurante`, `cliente`).
* **Login de usuário**: `POST /auth?action=login`
    * Realiza o login do usuário com email e senha, retornando um token JWT.

### Usuários
* **Obter perfil do usuário autenticado**: `GET /usuarios/perfil`
    * Retorna os dados do perfil do usuário autenticado via token JWT.
* **Atualizar perfil do usuário autenticado**: `PUT /usuarios/perfil`
    * Atualiza nome e email do usuário autenticado.

### Restaurantes
* **Listar restaurantes do usuário autenticado**: `GET /restaurantes`
    * Retorna a lista de restaurantes pertencentes ao usuário autenticado.
* **Criar novo restaurante**: `POST /restaurantes`
    * Cria um restaurante novo para o usuário autenticado.
* **Obter detalhes de um restaurante**: `GET /restaurantes/{id}`
    * Retorna detalhes do restaurante pelo ID.
* **Atualizar restaurante**: `PUT /restaurantes/{id}`
    * Atualiza nome e endereço do restaurante.
* **Deletar restaurante**: `DELETE /restaurantes/{id}`
    * Deleta o restaurante pelo ID.

### Mesas
* **Listar mesas de um restaurante**: `GET /restaurantes/{id}/mesas`
    * Retorna a lista de mesas de um restaurante específico, com opções de paginação e filtro por status.
* **Criar nova mesa para um restaurante**: `POST /restaurantes/{id}/mesas`
    * Cria uma nova mesa vinculada ao restaurante indicado.
* **Obter detalhes de uma mesa**: `GET /restaurantes/{restauranteId}/mesas/{mesaId}`
    * Retorna detalhes de uma mesa específica de um restaurante.
* **Atualizar mesa**: `PUT /restaurantes/{restauranteId}/mesas/{mesaId}`
    * Atualiza todos os dados da mesa especificada.
* **Atualizar parcialmente uma mesa**: `PATCH /restaurantes/{restauranteId}/mesas/{mesaId}`
    * Atualiza parcialmente os dados da mesa especificada.
* **Deletar mesa**: `DELETE /restaurantes/{restauranteId}/mesas/{mesaId}`
    * Remove a mesa especificada do restaurante.

### Reservas
* **Listar reservas**: `GET /reservas`
    * Retorna lista paginada de reservas com filtros opcionais por status, restaurante e usuário.
* **Criar nova reserva**: `POST /reservas`
    * Cria uma nova reserva para uma mesa em um restaurante.
* **Obter detalhes de uma reserva**: `GET /reservas/{id}`
    * Retorna detalhes da reserva pelo ID.
* **Atualizar reserva**: `PUT /reservas/{id}`
    * Atualiza todos os dados da reserva especificada.
* **Atualizar parcialmente uma reserva**: `PATCH /reservas/{id}`
    * Atualiza parcialmente os dados da reserva especificada.
* **Deletar reserva**: `DELETE /reservas/{id}`
    * Remove a reserva especificada.

### Avaliações
* **Listar avaliações de um restaurante**: `GET /restaurantes/{restauranteId}/avaliacoes`
    * Retorna lista paginada de avaliações do restaurante.
* **Criar nova avaliação para um restaurante**: `POST /restaurantes/{restauranteId}/avaliacoes`
    * Cria uma avaliação para o restaurante autenticado (apenas usuários clientes).
* **Obter detalhes de uma avaliação**: `GET /restaurantes/{restauranteId}/avaliacoes/{avaliacaoId}`
    * Retorna detalhes da avaliação especificada.
* **Atualizar avaliação**: `PUT /restaurantes/{restauranteId}/avaliacoes/{avaliacaoId}`
    * Atualiza todos os dados da avaliação especificada.
* **Deletar avaliação**: `DELETE /restaurantes/{restauranteId}/avaliacoes/{avaliacaoId}`
    * Remove a avaliação especificada.

## 🔒 Segurança

A API utiliza `BearerAuth` com tokens JWT para autenticação.

## 🗄️ Esquema do Banco de Dados

O banco de dados `mesalivre` possui as seguintes tabelas:

* **`mesas`**:
    * `id`: ID gerado pela aplicação (VARCHAR(36))
    * `numero`: Número da mesa (INT)
    * `capacidade`: Capacidade da mesa (INT)
    * `status`: Status da mesa (`disponivel`, `reservada`, `indisponivel`)
    * `restaurante_id`: ID do restaurante (VARCHAR(36))
    * `excluido`: (TINYINT(1))
    * `data_criacao`: (TIMESTAMP)
    * `data_atualizacao`: (TIMESTAMP)

* **`reservas`**:
    * `id`: ID gerado pela aplicação (VARCHAR(36))
    * `cliente_id`: ID do cliente (VARCHAR(36))
    * `restaurante_id`: ID do restaurante (VARCHAR(36))
    * `mesa_id`: ID da mesa (VARCHAR(36))
    * `data_reserva`: Data da reserva (DATE)
    * `hora_inicio`: Hora de início da reserva (TIME)
    * `hora_fim`: Hora de fim da reserva (TIME)
    * `status`: Status da reserva (`pendente`, `confirmada`, `cancelada`)
    * `observacoes`: Observações (TEXT)
    * `excluido`: (TINYINT(1))
    * `data_criacao`: (TIMESTAMP)

* **`restaurantes`**:
    * `id`: ID gerado pela aplicação (VARCHAR(36))
    * `nome`: Nome do restaurante (VARCHAR(100))
    * `descricao`: Descrição (TEXT)
    * `endereco`: Endereço (VARCHAR(255))
    * `telefone`: Telefone (VARCHAR(20))
    * `usuario_id`: ID do usuário (VARCHAR(36))
    * `excluido`: (TINYINT(1))
    * `data_criacao`: (TIMESTAMP)
    * `culinaria`: (VARCHAR(255))

* **`tokens_reset_senha`**:
    * `id`: (INT)
    * `email`: Email (VARCHAR(150))
    * `token_reset`: Token de reset de senha (VARCHAR(255))
    * `data_criacao`: (TIMESTAMP)

* **`usuarios`**:
    * `id`: ID gerado pela aplicação (VARCHAR(36))
    * `nome`: Nome do usuário (VARCHAR(100))
    * `email`: Email (VARCHAR(150))
    * `senha`: Senha (VARCHAR(255))
    * `tipo_usuario`: Tipo de usuário (`admin`, `restaurante`, `cliente`)
    * `excluido`: (TINYINT(1))
    * `data_criacao`: (TIMESTAMP)
    * `data_atualizacao`: (TIMESTAMP)
