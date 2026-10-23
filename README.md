# Agendamento de barbearia

Sistema desenvolvido para o desafio técnico Laravel da Skala Code. Permite cadastrar serviços, receber agendamentos pela página pública e consultar a agenda diária em uma área restrita a funcionários.

O cliente escolhe o serviço, a data e um horário disponível e informa nome e telefone, sem precisar criar uma conta. O barbeiro acessa a agenda, cadastra serviços e acompanha o valor previsto dos atendimentos do dia.

## Tecnologias e requisitos

O projeto utiliza Laravel 13, Livewire 4, Blade e SQLite. O layout usa Tailwind CSS 4 via CDN. Os testes são escritos com Pest 5, com Laravel Pint para formatação e PHPStan com Larastan para análise estática.

Para reproduzir o ambiente de desenvolvimento e do CI, utilize:

- PHP 8.5, versão recomendada e utilizada na validação do projeto.
- Composer 2 para instalar as dependências registradas no `composer.lock`.
- Git para clonar o repositório. Como alternativa, é possível baixar e extrair o ZIP pelo GitHub.
- Extensões PHP exigidas pelas dependências, incluindo `pdo_sqlite`, `sqlite3`, `mbstring`, `dom`, `xml` e `xmlwriter`.
- Extensão `zip` ou um extrator compatível, como `unzip` ou 7-Zip disponível no PATH, para extrair os pacotes do Composer.
- Conexão à internet para instalar as dependências e carregar os estilos do Tailwind via CDN.

Não é necessário instalar Node.js, npm, Vite ou um servidor separado de banco de dados. O SQLite utiliza um arquivo local. PHP e Composer precisam estar disponíveis no terminal usado para executar os comandos.

As regras de disponibilidade ficam em `app/BookingSchedule.php`. As páginas e o layout compartilhado estão em `resources/views/`, as rotas em `routes/`, as configurações em `config/` e as migrations, factories e seeders em `database/`. Os testes ficam em `tests/`, e o workflow de integração contínua está em `.github/workflows/tests.yml`.

## Instalação em outra máquina

### 1. Conferir as ferramentas

Abra um terminal e verifique as instalações:

```bash
php --version
composer --version
git --version
```

### 2. Clonar e preparar a aplicação

Execute em uma pasta na qual você possa criar o projeto:

```bash
git clone https://github.com/Guilher-mee/barbearia-skala.git
cd barbearia-skala
composer setup
composer check-platform-reqs
```

Se utilizar o ZIP, extraia o conteúdo e abra o terminal na pasta que contém `composer.json`, antes de executar os dois comandos do Composer.

O comando `composer setup` instala as versões de dependências fixadas no lockfile, copia `.env.example` para `.env` quando necessário, cria o arquivo SQLite, executa as migrations e prepara a chave da aplicação caso ela esteja ausente. Em `local` e `testing`, também prepara os serviços de exemplo e a conta demonstrativa de funcionário. Não são criados agendamentos automaticamente.

O comando `composer check-platform-reqs` confere os requisitos das dependências instaladas. O acesso ao SQLite também depende das extensões PHP indicadas acima.

Cada instalação possui seu próprio banco. Não copie o `.env`, o banco preenchido ou a pasta `vendor` de outra máquina para simular uma instalação nova. O `.env.example` define `APP_ENV=local`, `DB_CONNECTION=sqlite` e sessões no banco; sem personalizar `DB_DATABASE`, o arquivo utilizado é `database/database.sqlite`.

O PHP precisa ter permissão de escrita no banco e nos diretórios `storage` e `bootstrap/cache`. No Windows, caso falte uma extensão, confira o arquivo de configuração carregado pelo PHP do terminal com `php --ini` e habilite a extensão nessa instalação.

### 3. Iniciar o servidor

```bash
composer dev
```

Abra no navegador o endereço informado pelo servidor. Mantenha o terminal aberto e use `Ctrl+C` para encerrar. Se já houver outra instância ocupando a porta padrão, utilize este comando no lugar de `composer dev`:

```bash
php artisan serve --port=8001 --no-interaction
```

Os comandos devem ser executados a partir da raiz do projeto. Durante o desenvolvimento, mantenha as configurações sem cache. Se tiver utilizado `config:cache`, execute o comando abaixo após alterar o `.env`:

```bash
php artisan config:clear --no-interaction
```

## Acesso demonstrativo e fluxo de avaliação

### Conta local de funcionário

Após o setup em uma instalação nova, use a opção **Área do barbeiro** ou acesse a rota `/entrar` com:

- **E-mail:** `admin@example.test`
- **Senha:** `DemoBarbearia!123`

Essas credenciais são públicas e destinadas somente à demonstração local. A conta possui permissão de funcionário para acessar a agenda e cadastrar serviços. Sua criação fica restrita a `local` e `testing`; a preparação completa e o seeder da conta recusam a criação de dados demonstrativos nos demais ambientes, inclusive em execução forçada.

Antes da primeira preparação, é possível definir `DEMO_STAFF_NAME`, `DEMO_STAFF_EMAIL` e `DEMO_STAFF_PASSWORD` no `.env` privado. A senha deve ter pelo menos 12 caracteres, com maiúsculas, minúsculas, números e símbolos. Essas configurações definem os valores de criação e não substituem contas existentes.

Reexecutar `composer setup` preserva a chave existente, os serviços, as reservas e as credenciais. Portanto, a senha demonstrativa não redefine uma senha já personalizada. Caso o e-mail escolhido pertença a um usuário sem permissão de funcionário, a preparação é interrompida, sem promover esse usuário automaticamente.

Para preparar apenas a chave ausente e os dados locais, em uma instalação cujas migrations já foram executadas:

```bash
php artisan app:prepare-local --no-interaction
```

Para criar um funcionário com credenciais próprias:

```bash
php artisan app:create-staff
```

Esse comando é interativo: solicita nome, e-mail, senha e confirmação, com entrada oculta da senha. Não deve receber `--no-interaction`. E-mails já cadastrados são recusados. Em produção, utilize contas com credenciais próprias, sem a conta demonstrativa.

### Rotas principais

| Rota | Finalidade |
| --- | --- |
| `/` | Agendamento público com seleção de serviço, data e horário |
| `/entrar` | Login de funcionário |
| `/servicos` | Cadastro e listagem de serviços, com acesso restrito |
| `/agenda` | Agenda diária e resumo dos valores previstos, com acesso restrito |
| `/sair` | Encerramento da sessão pelo formulário de saída, usando POST |

### Roteiro para testar o sistema

Em uma instalação nova, abra uma janela privada do navegador para evitar aproveitar uma sessão de outra instância.

1. Confira que a página pública mostra os serviços de exemplo e permite iniciar um agendamento sem login. Tente abrir a agenda sem autenticação e confirme o redirecionamento para a entrada.
2. Entre com a conta demonstrativa e cadastre um serviço chamado Corte, com duração de 40 minutos e preço de R$ 50,00.
3. Use **Sair**, volte à página pública e selecione esse serviço. Escolha amanhã no calendário e um horário livre, como 09:00 até 09:40. Informe nome e telefone fictícios, por exemplo Ana Silva e `11912345678`, e confirme.
4. Entre novamente como funcionário, abra a agenda e selecione a mesma data. Confira o atendimento e o valor previsto de R$ 50,00. Em um banco novo, sem outras reservas para essa data, haverá um único agendamento.
5. Em outro terminal, na mesma pasta, reexecute `composer setup`. Confirme que o serviço e a reserva continuam presentes, sem duplicação dos dados iniciais ou alteração da senha.

O calendário permite navegar entre meses futuros. Datas passadas ficam desabilitadas, e os horários apresentam os estados Livre, Reservado ou Já passou. Somente horários disponíveis podem ser escolhidos. Se outra pessoa reservar o intervalo antes da confirmação, a disponibilidade é atualizada e os dados de contato preenchidos são preservados para outra escolha.

### Dados de exemplo opcionais

O setup prepara serviços, mas mantém a agenda vazia. Para preparar os serviços separadamente:

```bash
php artisan db:seed --class=ServiceSeeder --no-interaction
```

Para inserir também reservas demonstrativas para amanhã:

```bash
php artisan db:seed --class=BookingSeeder --no-interaction
```

O seeder de reservas reutiliza os serviços existentes e ignora os intervalos que já estão ocupados.

## Funcionalidades entregues

**Serviços.** O funcionário cadastra e consulta serviços com nome, duração em minutos e preço. A entrada recebe validação no servidor, e os valores são apresentados em reais. A interface contempla criação e listagem; edição e exclusão não fazem parte desta entrega.

**Agendamento público.** O cliente escolhe um serviço, uma data no calendário mensal e um intervalo disponível. Informa nome e telefone e recebe a confirmação após a validação do horário no servidor. Não há necessidade de criar uma conta.

**Disponibilidade e prevenção de conflitos.** O término é calculado a partir da duração do serviço. A reserva precisa caber integralmente no expediente e não pode coincidir com outro atendimento, mesmo de um serviço diferente. A criação também trata solicitações concorrentes para o mesmo intervalo.

**Área do barbeiro.** Funcionários autenticados consultam a agenda por data, em ordem cronológica, com serviço e contato do cliente. Cadastro de serviços, consultas e atualizações Livewire exigem autorização. O login limita tentativas inválidas, e a saída encerra a sessão.

**Valores previstos.** A agenda mostra a quantidade de reservas e o total dos preços registrados no momento de cada agendamento. Esse valor é uma previsão, sem confirmação de pagamento ou de atendimento realizado. Reservas antigas sem preço registrado são identificadas separadamente e não entram no total.

**Preparação e qualidade.** O projeto possui setup local repetível, conta demonstrativa configurável, testes automatizados de funcionalidades e concorrência, formatação, análise estática e integração contínua sem etapas de frontend com Node.

## Decisões técnicas e limites

**Uma agenda compartilhada.** Há um atendimento por vez para toda a barbearia. Não foi criado um cadastro de múltiplos barbeiros nem uma agenda independente por profissional. Serviços diferentes disputam os mesmos intervalos.

**Expediente e grade de horários.** O início às 09:00 e o término exatamente às 18:00 são permitidos. Os intervalos oferecidos partem das 09:00, em incrementos iguais à duração do serviço: para 40 minutos, a grade começa em 09:00, 09:40 e 10:20. Não há deslocamento automático da grade para preencher lacunas, nem cadastro de pausas, feriados ou dias fechados.

**Datas e fuso horário.** As datas exibidas seguem `America/Sao_Paulo`, configurado em `config/booking.php`, e os horários são armazenados em UTC. Não é permitido reservar no passado ou no instante atual. Não foi definido limite máximo de antecedência.

**Intervalos adjacentes e concorrência.** Um atendimento pode começar exatamente quando o anterior termina. Há conflito quando o início existente é anterior ao novo término e o término existente é posterior ao novo início. A criação utiliza uma transação SQLite `IMMEDIATE`, relê o serviço e verifica os conflitos antes de inserir. O banco aguarda até cinco segundos pelo bloqueio; caso esse tempo seja excedido, a interface solicita uma nova tentativa.

**Validação dos dados.** A duração do serviço deve ser inteira, entre 1 e 540 minutos. O preço deve ser não negativo e ter até duas casas decimais. Nomes de serviços repetidos são permitidos. O nome do cliente é obrigatório, e o telefone aceita de 10 a 15 dígitos, com prefixo `+`, espaços, parênteses e hífens.

**Histórico do atendimento e do valor.** O término e o preço da reserva são persistidos. Mudanças posteriores no catálogo não recalculam esses valores. O resumo soma os preços em centavos para evitar cálculos monetários com ponto flutuante. Quando o preço de uma reserva antiga é desconhecido, ele permanece nulo, sem estimativa baseada no catálogo atual.

**Escolha das ferramentas.** Livewire, Blade e Tailwind via CDN mantêm a interface simples e dispensam a compilação de assets. O SQLite permite executar o projeto sem provisionar um servidor de banco. Não há integração com calendários externos; a disponibilidade é calculada a partir das reservas persistidas para o dia selecionado.

**Arquivos privados.** O `.env.example` é um modelo público de configuração. Credenciais próprias devem permanecer no `.env` privado. O Git ignora arquivos de ambiente privados, bancos SQLite, chaves, backups, exportações e artefatos de execução. A conta demonstrativa e as senhas fictícias dos testes não representam credenciais de produção.

## Testes e qualidade

Execute os comandos em outro terminal, na raiz do projeto, após instalar as dependências:

```bash
# Suíte completa
php artisan test --compact

# Preparação local e preservação de dados
php artisan test --compact tests/Feature/LocalSetupTest.php

# Autenticação e provisionamento de funcionários
php artisan test --compact tests/Feature/StaffAuthenticationTest.php tests/Feature/CreateStaffTest.php

# Cadastro e agendamento pela interface
php artisan test --compact tests/Feature/ManageServicesTest.php tests/Feature/BookAppointmentTest.php

# Agenda, preços registrados e resumo diário
php artisan test --compact tests/Feature/DailyAgendaTest.php tests/Feature/BookingPriceTest.php tests/Feature/DailySummaryTest.php

# Regras de disponibilidade e concorrência
php artisan test --compact tests/Feature/BookingScheduleTest.php tests/Feature/BookingConcurrencyTest.php

# Análise estática
composer types:check

# Aplicar a formatação do projeto
composer lint

# Verificações utilizadas pelo CI: estilo, análise estática e testes
composer ci:check
```

Os testes comuns utilizam SQLite em memória e factories, sem utilizar o banco da instalação local. Os testes de concorrência criam dois processos PHP e um arquivo SQLite temporário, com sincronização por pipes. O ambiente precisa permitir a criação de subprocessos e a escrita no diretório temporário.

O workflow do GitHub Actions executa o setup e as verificações em PHP 8.5 com Composer 2, sem instalar Node. Os comandos não dependem de um banco preenchido de outra máquina.

## Planejado versus entregue

O fluxo principal priorizou a persistência dos serviços e reservas, o expediente, a disponibilidade e a prevenção de conflitos. A validação evoluiu para contemplar disputas concorrentes pelo mesmo intervalo, além das verificações de formulário e persistência.

As interfaces foram construídas com páginas Livewire e um layout compartilhado. A seleção de datas foi aprimorada com um calendário mensal em português e horários com estados de disponibilidade, mantendo as regras de agendamento no servidor e dispensando dependências de frontend adicionais.

Em uma etapa posterior, a área interna passou a exigir autenticação e autorização de funcionários. O agendamento permaneceu público para evitar ampliar o escopo com contas de clientes. Também foram adicionados o registro do preço da reserva e um resumo diário dos valores previstos.

A preparação local foi simplificada para permitir uma instalação reproduzível, com serviços e acesso demonstrativo e preservação dos dados nas reexecuções. A estrutura de testes e o workflow de CI acompanham esses fluxos.

Cancelamento, remarcação e lembretes ficaram fora da entrega para manter o foco no fluxo principal e na sua validação. O resumo diário não constitui um controle financeiro de pagamentos. Também não foram implementados edição e exclusão de serviços, gestão de funcionários pela interface ou múltiplas agendas por profissional. As instruções deste documento cobrem a execução local, sem um procedimento de implantação em produção.

## Uso de IA

O Codex foi utilizado no planejamento, na análise do repositório, na implementação e nas verificações automatizadas. As solicitações foram refinadas ao longo do desenvolvimento, com revisão do escopo e dos resultados.

Os cinco itens abaixo são sínteses retrospectivas dos principais pedidos, agrupadas por tema.

1. **Requisitos, escopo e modelagem.** Analisar o enunciado do desafio, priorizar uma entrega viável e definir a estrutura de serviços e agendamentos, com seus relacionamentos, duração, preço e dados necessários para iniciar o sistema.

2. **Regras e consistência das reservas.** Implementar a disponibilidade e a criação de agendamentos dentro do expediente, calcular o término no servidor e impedir sobreposições, inclusive quando duas solicitações disputarem o mesmo intervalo. Verificar também os limites dos horários e os cenários de conflito com testes automatizados.

3. **Interfaces e experiência de agendamento.** Construir as páginas de cadastro de serviços, agendamento público e agenda do barbeiro com Blade e Livewire. Evoluir a escolha de datas para um calendário mensal com estados de disponibilidade, mantendo Tailwind via CDN e preservando os dados preenchidos quando surgir um conflito.

4. **Acesso restrito e valores previstos.** Diferenciar a navegação de clientes e funcionários sem exigir conta do cliente, proteger as operações internas e registrar o preço de cada reserva para apresentar um resumo diário dos valores agendados, sem tratá-los como pagamentos recebidos.

5. **Instalação reproduzível e qualidade.** Simplificar a execução em outra máquina, preparar serviços e acesso demonstrativo sem sobrescrever dados existentes, proteger arquivos privados e verificar os fluxos com testes de funcionalidades, autorização e concorrência, além de formatação e análise estática.
