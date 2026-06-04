# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies and setup
composer setup          # install deps, generate key, run migrations, npm install

# Development (runs server + queue + logs + Vite concurrently)
composer dev

# Testing
composer test           # runs PHPUnit
php artisan test        # equivalent
php artisan test --filter=RelevanceFilterTest   # run a single test class

# Static analysis
vendor/bin/phpstan analyse  # Level 5, app/ only

# Frontend
npm run dev             # Vite dev server
npm run build           # production build
```

### Docker (alternative to local dev)
```bash
make setup              # full Docker setup (build, up, composer install, migrate, seed)
make up                 # docker compose up -d
make stop               # docker compose stop
make data               # run migrations and seeders
make destroy            # destroy all containers, images, volumes
```

The Docker stack runs: PHP-FPM (`app`), Laravel Horizon (`horizon`), Nginx on port 8080, MySQL 8.0, Redis 7.0.

## Architecture

This is a **multi-site product search aggregator** for tools/equipment (ferramentas). Users search for a product, the system scrapes 8 Brazilian e-commerce sites in parallel, scores results by relevance, and identifies the cheapest option.

### Search Pipeline

1. User POSTs `/ferramentas/buscar` → creates `FerramentaBusca` record → dispatches `BuscarFerramentaJob`
2. `BuscarFerramentaJob` creates a `Bus::batch()` of `BuscarNoSiteJob` (one per site, `allowFailures()`)
3. Each `BuscarNoSiteJob` calls `CrawlerService` → site-specific scraper → `RelevanceFilter`
4. Results persisted to `ResultadoBusca` as jobs complete
5. Batch completion triggers `FinalizarBuscaJob` → marks cheapest item globally
6. Frontend polls `/ferramentas/{id}/status` for real-time updates

### Scraper Layer (`app/Services/Scrapers/`)

- `ScraperInterface` / `BaseScraper`: template method pattern — subclasses implement `executarBusca()`
- `RelevanceFilter`: multi-field scoring (title 1.0×, code 1.0×, description 0.75×). Uses exact match → Portuguese stemming → synonyms → fuzzy (≥85% similarity for 5+ char tokens). Discards scores < 0.5 and kit/bundle results unless the query explicitly asks for them.
- `QueryNormalizer`: lowercases, removes accents, splits into tokens, filters Portuguese stopwords
- `CrawlerService`: factory that maps site names to scraper class instances
- Scraper diversity: Kennedy uses Inertia.js (extracts `data-page` JSON); Mercado Livre uses OAuth2 API (tokens cached 6h); all others parse HTML via Symfony DomCrawler

### Data Models

- `User` — auth + Stripe customer (Cashier)
- `FerramentaBusca` — search query (term, lojas filter, status, total_sites); belongs to User
- `ResultadoBusca` — one result per product found (site, name, price, url, image, cheapest flag); belongs to FerramentaBusca
- `Orcamento` / `OrcamentoItem` — quote management with PDF export (barryvdh/laravel-dompdf)

### Route Protection

```
Public:          /login, /register, /forgot-password, etc.   (Breeze)
Auth only:       /assinatura/*                                (checkout, Stripe portal)
Auth+Subscribed: /ferramentas/*, /orcamentos/*               (search, quotes)
```

Trial period: 7 days. Subscription currency: BRL.

### Queue & Background Processing

- Driver: Redis (production), database (test env)
- Horizon dashboard available for queue monitoring
- Job timeout: 90s, retries: 2 (configured in `BuscarNoSiteJob`)

### Testing

- Unit tests in `tests/Unit/Scrapers/` cover `RelevanceFilter` (25+ cases) and `QueryNormalizer`
- Feature tests in `tests/Feature/` cover auth flows
- Test database configured in `.env.test` (coreproject_test on 127.0.0.1)
- CI runs PHPStan then PHPUnit on every push to main/develop/feature/*
