
# 🏦 Sistema Bancário API

### Documentação Técnica e Guia de Integração
---

## 👨‍💻 Autores e Identificação
* **Autor 1:** Afonso José Lopes Carvalho  **Nº:** 049948
* **Autor 2:** Rui Filipe Sousa Passos  **Nº:** 048863
* **Instituição:** Instituto Politécnico da Maia IPMAIA
* **Tecnologia Base:** Laravel 11.x (PHP 8.2+) & SQLite

---

## 📑 Resumo do Projeto
Este projeto consiste no desenvolvimento de uma **API RESTful** de alto rendimento que simula o *Core* transacional de uma instituição bancária digital. A arquitetura foi desenhada com foco na segurança absoluta dos saldos, imutabilidade dos registos financeiros, conformidade ACID e escalabilidade através de técnicas avançadas de indexação e paginação.

---

## ✨ Funcionalidades Avançadas

### 1. Integridade Monetária com Tipo Decimal (15,4)
Ao contrário de aplicações académicas normais que utilizam tipos de dados `float` ou `double`, esta API armazena e processa todos os valores monetários usando o formato nativo matemático **`DECIMAL(15,4)`**. Isto elimina por completo os erros catastróficos de arredondamento por vírgula flutuante (*floating-point binary inaccuracies*) ao nível do CPU, garantindo consistência contabilística até à quarta casa decimal.

### 2. Transações Atómicas e Resiliência ACID
Todas as mutações de estado financeiro (Depósitos, Levantamentos e especialmente Transferências entre contas diferentes) são executadas sob o ecossistema de **Transações de Base de Dados (`DB::transaction`)**. 
* Numa transferência, se o débito na Conta A for bem-sucedido, mas o crédito na Conta B falhar devido a uma quebra de ligação ou erro do servidor, o Laravel aciona instantaneamente um **`Rollback`**.
* O dinheiro nunca desaparece nem fica duplicado no limbo do sistema, assegurando consistência estrita.

### 3. Blindagem de Saldos e Resposta de Erro Padronizada (HTTP 422)
Seguindo as especificações rigorosas do enunciado, o motor de levantamentos e transferências valida previamente a suficiência de fundos. Tentativas de movimentação ilícita que resultem em saldos negativos são imediatamente intercetadas e abortadas, respondendo com o estado **`422 Unprocessable Entity`** acompanhado de uma mensagem JSON estruturada.

### 4. Paginação por Cursor de Alta Performance
Para a listagem de extratos e históricos de transações volumosos, a aplicação abandona o método tradicional de paginação por *Offset* (que degrada exponencialmente a performance à medida que a tabela cresce) e adota a **Paginação por Cursor (`cursorPaginate`)**. 
O sistema lê os registos com base num ponteiro codificado indexado temporariamente, garantindo tempos de resposta constantes de escassos milissegundos, ideais para ecrãs de *Infinite Scroll*.

### 5. Algoritmo de Amortização Financeira (Sistema Francês)
O simulador de empréstimos utiliza a fórmula avançada de amortização com prestações constantes (Tabela Price / Sistema Francês):

$$P = V \\frac{i(1+i)^n}{(1+i)^n - 1}$$

O algoritmo efetua um ciclo dinâmico mês a mês para cindir com precisão cirúrgica a quota de juros cobrada sobre o capital em dívida face à quota de amortização real que abate o saldo devedor até à sua total extinção no termo do contrato.

---

## 🗂 Estrutura do Modelo de Dados (BD)

A base de dados está organizada de forma relacional através de 4 entidades fundamentais:

1. **`users` (Utilizadores):** Guarda os metadados dos clientes. Inclui campos adicionais obrigatórios como NIF único, Data de Nascimento e o Código PIN trancado criptograficamente.

2. **`accounts` (Contas):** Entidade financeira pura. Contém o número de conta exclusivo (IBAN gerado com prefixo PT50 e proteção contra colisões) e o saldo atualizado.

3. **`account_user` (Pivot Muitos-para-Muitos):** Tabela intermédia avançada que liga utilizadores a contas, definindo o nível de privilégio através da coluna `role` (`owner` ou `member`). Esta estrutura permite, de forma nativa, a expansão futura para contas conjuntas/empresariais.

4. **`transactions` (Livro Razão / Ledger):** Registo imutável de movimentos. Contém referências únicas (ex: `TRF-...`), tipo de operação, montantes e o estado histórico crucial do saldo imediatamente após a operação (`balance_after`).

---

## 🗺 Matriz de Endpoints e Rotas da API

| Método | Endpoint | Função Técnica | Segurança | Payloads / Parâmetros |
| :--- | :--- | :--- | :--- | :--- |
| **POST** | `/api/register` | Criação de cliente e chave criptográfica | Pública | `name`, `email`, `password`, `nif`, `birth_date`, `pin_code` |
| **POST** | `/api/login` | Autenticação e emissão de Bearer Token | Pública | `email`, `password` |
| **POST** | `/api/accounts` | Abertura de conta bancária com IBAN PT50 | 🔐 Sanctum | Nenhum (vinculado ao token do utilizador) |
| **GET** | `/api/accounts/{id}/balance` | Consulta em tempo real do saldo | 🔐 Sanctum | ID da Conta (Bloqueio 403 se não for titular) |
| **POST** | `/api/accounts/{id}/deposit` | Injeção de fundos monetários positivos | 🔐 Sanctum | `amount` (Numérico positivo) |
| **POST** | `/api/accounts/{id}/withdraw` | Levantamento com validação de saldo | 🔐 Sanctum | `amount` (Retorna 422 se saldo insuficiente) |
| **POST** | `/api/transfers` | Transferência atómica entre duas contas | 🔐 Sanctum | `source_account_id`, `destination_account_id`, `amount` |
| **GET** | `/api/accounts/{id}/transactions` | Histórico cru de movimentos por cursor | 🔐 Sanctum | ID da Conta (Retorna paginação estruturada) |
| **GET** | `/api/accounts/{id}/statement` | Extrato avançado com filtros dinâmicos | 🔐 Sanctum | `type`, `start_date`, `end_date` (Query Params) |
| **POST** | `/api/loans/simulate` | Cálculo matemático de amortização | Pública | `amount`, `term_months`, `interest_rate` |

---

## 🚀 Guia Prático de Instalação e Execução

Siga os passos abaixo sequencialmente para colocar a API funcional no seu ambiente local em menos de 3 minutos:

### 1. Descarregar o Projeto

```bash
    git clone [https://github.com/afonsojlc/sistema-bancario-api.git](https://github.com/afonsojlc/sistema-bancario-api.git)
```


```bash
    cd sistema-bancario-api
```

### 2. Instalar Dependências do PHP

```bash
    composer install
```

### 3. Configurar Ficheiro de Ambiente

```bash
    cp .env.example .env    
```
```bash
    php artisan key:generate
```

(Por padrão, o Laravel 11 vem pré-configurado para SQLite. Não necessita de instalar servidores locais pesados de MySQL, a base de dados correrá num ficheiro local isolado gerado no próximo passo).

### 4. Executar as Migrations do Sistema

```bash
    php artisan migrate
```

Nota: Se o terminal perguntar se deseja criar o ficheiro SQLite da base de dados, confirme digitando yes ou y.

### 5. Arrancar o Servidor de Desenvolvimento

```bash
    php artisan serve
```

O servidor ficará ativo em: http://127.0.0.1:8000. As suas rotas de API estarão prontas a responder sob o prefixo /api.

---

## 🗃 Instruções de Teste Automatizado com o Postman

Na raiz do projeto, encontra os dois ficheiros de configuração profissional gerados para auditoria da professora:

    1. Sistema_Bancario_API_Postman_Collection.json (A coleção completa de pedidos).

    2. Sistema_Bancario_Local_Environment.json (O ambiente com as variáveis locais).

### Passos para Correr os Testes:

1. Abra a aplicação do Postman.

2. Clique no botão "Import" no canto superior esquerdo e selecione ambos os ficheiros JSON instalados na raiz do projeto.

3. No canto superior direito do Postman, clique na caixa de seleção de ambiente e selecione "Sistema Bancário - Ambiente Local".

4. Execute o pedido na pasta 1. Autenticação -> Registar Novo Cliente. O Postman executará um script em segundo plano que captura automaticamente o token gerado e o armazena na variável de sessão global.

5. Pode testar imediatamente qualquer outra rota trancada (como Criar Conta ou Depósito). O token será injetado de forma transparente no cabeçalho de autorização.
---
