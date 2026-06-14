
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
Este projeto consiste no desenvolvimento de uma **API RESTful** de alto rendimento que simula o *Core* transacional de uma instituição bancária digital moderna (estilo FinTech). A arquitetura foi desenhada com foco na segurança absoluta dos saldos, imutabilidade dos registos financeiros, conformidade ACID, escalabilidade através de técnicas avançadas de *Caching* e funcionalidades inteligentes de poupança.

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

### 4. Autenticação "Step-Up" (Grau Militar) 🔒
O sistema implementa uma camada de segurança dupla utilizada por bancos de topo:
* **Sessão:** Protegida via Tokens de Autenticação (`Laravel Sanctum`).
* **Validação Crítica:** Sempre que o dinheiro *sai* da conta (Levantamentos, Transferências ou Pagamentos), a API exige a assinatura da operação através de um **Código PIN de 4 dígitos**. Este PIN está encriptado unidirecionalmente na base de dados e é validado em tempo real via `Hash::check()`.

### 5. Motor Cambial Multi-Moeda (com Caching) 💱
A API suporta contas em diferentes moedas globais (EUR, USD, GBP, JPY, etc.). Quando ocorre uma transferência transfronteiriça, o sistema consulta a **Frankfurter API** (Dados do Banco Central Europeu). 
* **Otimização de Escalabilidade:** Para evitar *Rate Limiting* (bloqueio por excesso de pedidos) e garantir respostas em milissegundos, as taxas de câmbio são memorizadas através da *Facade* `Cache` do Laravel por 3600 segundos (1 hora).

### 6. Cofres de Poupança (Vaults) & "Spare Change" 💰
Inspirado em neobancos líderes de mercado, o projeto incorpora a funcionalidade de "Poupança Indolor":
* **Cofres (Vaults):** O utilizador pode criar múltiplos cofres com objetivos de poupança isolados da conta principal (com herança e câmbio automático de moeda).
* **Arredondamento Automático (Spare Change):** Ao efetuar um Pagamento (ex: 4.20€), a API calcula o teto matemático (`ceil()`) da operação (5.00€), debita o valor da compra, e transfere cirurgicamente os cêntimos de troco (0.80€) diretamente para o Cofre de Poupança ativo no exato mesmo milissegundo.

### 7. Paginação por Cursor de Alta Performance
Para a listagem de extratos e históricos de transações volumosos, a aplicação abandona o método tradicional de paginação por *Offset* (que degrada exponencialmente a performance à medida que a tabela cresce) e adota a **Paginação por Cursor (`cursorPaginate`)**. 
O sistema lê os registos com base num ponteiro codificado indexado temporariamente, garantindo tempos de resposta constantes de escassos milissegundos, ideais para ecrãs de *Infinite Scroll*.

### 8. Extratos Paginados e Dinâmicos (Ledger) 📜
Os históricos de transações não sobrecarregam a memória do servidor. A extração de dados utiliza `cursorPaginate()` e formata a resposta JSON em tempo real. O utilizador recebe um extrato limpo com dados da contraparte (Ex: *"Transferência recebida de João Silva"*), espelhando a experiência do utilizador do Revolut ou Moey!.

### 9. Algoritmo de Amortização Financeira (Sistema Francês)
O simulador de empréstimos utiliza a fórmula avançada de amortização com prestações constantes (Tabela Price / Sistema Francês):

$$P = V \\frac{i(1+i)^n}{(1+i)^n - 1}$$

O algoritmo efetua um ciclo dinâmico mês a mês para cindir com precisão cirúrgica a quota de juros cobrada sobre o capital em dívida face à quota de amortização real que abate o saldo devedor até à sua total extinção no termo do contrato.

---

## 🗂 Estrutura do Modelo de Dados (BD)

A base de dados está desenhada de forma relacional e rigorosamente normalizada, suportada por **5 entidades fundamentais** que garantem a escalabilidade e a integridade matemática de todo o ecossistema financeiro:

1. **`users` (Utilizadores):** Guarda os metadados dos clientes. Para além da autenticação padrão (suportada por tokens Sanctum), inclui campos de negócio obrigatórios como o NIF (com restrição de unicidade), a Data de Nascimento e, crucialmente, o Código PIN de 4 dígitos. Este PIN é trancado criptograficamente na base de dados (via `Hash`), sendo o pilar da arquitetura de segurança *Step-Up* exigida nas operações de saída de capital.

2. **`accounts` (Contas Bancárias):** A entidade financeira principal. Contém o número de conta exclusivo (um IBAN gerado dinamicamente com o prefixo PT50 e mecanismo anti-colisão), a moeda base da conta (`currency` — que serve de âncora ao motor cambial) e o saldo corrente. Para evitar anomalias informáticas de arredondamento, o saldo é estritamente armazenado com alta precisão matemática (`DECIMAL(15,4)`).

3. **`account_user` (Pivot Muitos-para-Muitos):** Tabela intermédia avançada que estabelece a ligação entre os utilizadores e as suas contas. Define o nível de privilégio de cada titular através da coluna `role` (ex: `owner` ou `member`). Esta abstração relacional permite, de forma nativa, que o sistema seja facilmente expandido no futuro para suportar contas conjuntas, contas solidárias ou contas empresariais com múltiplos gestores.

4. **`transactions` (Livro Razão / *Ledger*):** O registo histórico e imutável de todos os movimentos financeiros. Cada operação gera uma referência alfanumérica única (ex: `TRF-...`, `PAY-...`). Armazena não só o montante e a moeda final debitada/creditada, mas também os dados da moeda de origem (`original_amount` e `original_currency`) para efeitos de auditoria de câmbios. Guarda ainda o estado crucial do saldo imediatamente após a operação (`balance_after`), garantindo a rastreabilidade exata do dinheiro no tempo. O campo taxonómico `type` classifica todas as operações da API: `DEPOSIT`, `WITHDRAWAL`, transferências interbancárias (`TRANSFER_IN`/`OUT`) e, mais recentemente, operações de poupança como `VAULT_FUNDING`, `VAULT_WITHDRAWAL`, pagamentos com cartão (`PAYMENT`) e captura de trocos (`SPARE_CHANGE`).

5. **`vaults` (Cofres de Poupança):** O núcleo do sistema de poupanças isoladas, responsável por guardar os diferentes objetivos financeiros do utilizador sem que estes se misturem com o saldo transacional corrente. Cada registo representa um cofre individual e está obrigatoriamente ligado a uma conta bancária através da chave estrangeira `account_id` (numa relação de 1:N). A tabela armazena dados descritivos como o nome do objetivo (`name`), a moeda isolada configurada para o cofre (`currency`), o saldo acumulado (`balance` em `DECIMAL(15,4)`) e uma meta financeira opcional (`target_amount`). O elemento tecnológico de maior destaque nesta tabela é o campo booleano `spare_change_active`, que atua como o "interruptor" lógico do sistema: quando ativado, autoriza a API a capturar invisivelmente os cêntimos de troco provenientes de compras arredondadas na conta principal, canalizando-os de forma atómica para o cofre.

---

## 🗺 Matriz de Endpoints e Rotas da API

| Método | Endpoint | Função Técnica | Segurança | Payloads / Parâmetros |
| :--- | :--- | :--- | :--- | :--- |
| **POST** | `/api/register` | Criação de cliente e chave criptográfica | Pública | `name`, `email`, `password`, `nif`, `birth_date`, `pin_code` |
| **POST** | `/api/login` | Autenticação e emissão de Bearer Token | Pública | `email`, `password` |
| **GET** | `/api/accounts/my-accounts` | Listagem de todas as contas do utilizador autenticado | 🔐 Sanctum | Nenhum |
| **POST** | `/api/accounts` | Abertura de conta bancária (IBAN PT50) e suporte Multi-Moeda | 🔐 Sanctum | `currency` (Opcional, default EUR) |
| **GET** | `/api/accounts/{id}/balance` | Consulta em tempo real do saldo formatado | 🔐 Sanctum | ID da Conta (Bloqueio 403 se não for titular) |
| **POST** | `/api/accounts/{id}/deposit` | Injeção de fundos monetários (com Câmbio Automático) | 🔐 Sanctum | `amount`, `currency` (Opcional) |
| **POST** | `/api/accounts/{id}/withdraw` | Levantamento com validação de saldo e motor cambial | 🔐 Sanctum + PIN | `amount`, `currency` (Opcional), `pin_code` |
| **POST** | `/api/transfers` | Transferência atómica interbancária com cruzamento de moedas | 🔐 Sanctum + PIN | `source_account_id`, `destination_account_id`, `amount`, `pin_code` |
| **POST** | `/api/accounts/{id}/payment` | Compra com cartão e motor invisível de "Spare Change" (Trocos) | 🔐 Sanctum + PIN | `amount`, `currency` (Opcional), `pin_code` |
| **GET** | `/api/accounts/{id}/transactions` | Histórico de movimentos paginado por cursor (*Ledger*) | 🔐 Sanctum | ID da Conta |
| **GET** | `/api/accounts/{id}/statement` | Extrato avançado com filtros dinâmicos de auditoria | 🔐 Sanctum | `type`, `start_date`, `end_date` (Query Params) |
| **GET** | `/api/vaults/my-vaults` | Listagem global de todos os Cofres de Poupança do cliente | 🔐 Sanctum | Nenhum |
| **POST** | `/api/accounts/{accountId}/vaults` | Criação de Cofre de Poupança (Herança e isolamento de moeda) | 🔐 Sanctum | `name`, `target_amount`, `currency` (Opcional) |
| **POST** | `/api/vaults/{id}/deposit` | Reforço do Cofre a partir do saldo da Conta Principal | 🔐 Sanctum | `amount` |
| **POST** | `/api/vaults/{id}/withdraw` | Resgate de fundos do Cofre de volta para a Conta Principal | 🔐 Sanctum | `amount` |
| **PATCH** | `/api/vaults/{id}/spare-change` | Interruptor LIGA/DESLIGA do arredondamento automático | 🔐 Sanctum | Nenhum |
| **POST** | `/api/loans/simulate` | Cálculo matemático do Sistema de Amortização Francês | 🔐 Sanctum | `amount`, `term_months`, `interest_rate`, `currency` |

---

## 🚀 Guia Prático de Instalação e Execução

Siga os passos abaixo sequencialmente para colocar a API funcional no seu ambiente local em menos de 3 minutos:

### 1. Descarregar o Projeto

```bash
git clone https://github.com/afonsojlc/sistema-bancario-api.git
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
## 🔮 Trabalhos Futuros (Visão Arquitetural)

O core do sistema encontra-se 100% estabilizado. Numa futura iteração (Sprint-2) do desenvolvimento deste software, propõe-se:

1.  **Portal de Back-Office (RBAC - Role-Based Access Control):** Criação de um tipo de utilizador `Admin` (Gerente) com privilégios de Compliance para congelar contas e reverter transações suspeitas de fraude.

2.  **Cron Jobs de Juros:** Utilização do *Task Scheduling* do Laravel para correr um script noturno de processamento em lote (*batch processing*), creditando micro-juros sobre os saldos mantidos nos Cofres de Poupança (Vaults).

3.  **Webhooks:** Emissão de alertas em tempo real para dispositivos móveis (Push Notifications) quando a conta recebe um crédito.
---
