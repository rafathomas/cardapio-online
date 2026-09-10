# Cardápio Online

Plataforma multi-tenant de cardápio digital para estabelecimentos (restaurantes, lanchonetes, etc), com pedidos via QR Code, gestão de produtos/categorias/adicionais, planos de assinatura com cobrança recorrente via Mercado Pago e painel administrativo.

## Stack

- **Backend:** Laravel 13 (PHP 8.3), Sanctum para autenticação de API
- **Frontend:** React 19 + React Router + TypeScript, Vite, Tailwind CSS 4
- **Banco de dados:** MySQL, Redis (cache e filas)
- **Pagamentos:** Mercado Pago (assinaturas, cobranças e webhooks)
- **Testes:** Pest 4 (unit, feature e browser)

## Principais funcionalidades

- Cadastro e gestão de estabelecimentos, categorias, produtos e grupos de adicionais
- Geração de QR Code para acesso ao cardápio do estabelecimento
- Fluxo de pedidos (carrinho, itens, adicionais e status do pedido)
- Planos e assinaturas com cobrança recorrente e webhooks de pagamento (Mercado Pago)
- Painel administrativo (usuários, estabelecimentos e faturamento)
- Notificação de pedidos via WhatsApp

## Requisitos

- PHP 8.3+
- Composer
- Node.js 20+ e npm
- MySQL e Redis (ou Docker via Laravel Sail)

## Configuração do ambiente

1. Copie o arquivo de variáveis de ambiente:
   ```bash
   cp .env.example .env
   ```
2. Instale as dependências e configure o projeto:
   ```bash
   composer run setup
   ```
   Esse comando instala as dependências PHP e JS, gera a `APP_KEY`, roda as migrations e builda os assets.
3. Preencha as credenciais do Mercado Pago (`MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_WEBHOOK_SECRET`) no `.env` para testar o fluxo de pagamentos.

## Desenvolvimento

Subir a aplicação (servidor Laravel + fila + logs + Vite) em um único comando:

```bash
composer run dev
```

Rodar apenas o frontend:

```bash
npm run dev
```

## Testes

```bash
composer run test
```

## Comandos artisan úteis

- `php artisan expire-subscriptions` — expira assinaturas vencidas
- `php artisan sync-pending-payments` — sincroniza pagamentos pendentes com o gateway
- `php artisan make-admin` — cria um usuário administrador
