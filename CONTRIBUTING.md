# Contribution Guide (CONTRIBUTING.md)

Thank you for your interest in contributing! This document describes the workflow, standards and tools of the **Shortener** project (URL shortener with HMAC-signed URLs and *privacy‑first* analytics).

> **Stack summary**: Laravel 12 (PHP 8.3), MySQL 8, Redis 7, Blade, queues (database/redis), multi‑stage Docker, GitHub Actions, Prometheus (/metrics), /health endpoint, security limits and headers.

---

## Table of Contents
- [Code of Conduct](#code-of-conduct)
- [Requirements](#requirements)
- [Local Environment Setup](#local-environment-setup)
- [Repository Structure](#repository-structure)
- [Git Workflow](#git-workflow)
- [Style and Quality](#style-and-quality)
- [Tests](#tests)
- [Commits and Messages](#commits-and-messages)
- [Pull Requests](#pull-requests)
- [CI/CD](#cicd)
- [Documentation](#documentation)
- [Security](#security)
- [Vulnerability Reporting](#vulnerability-reporting)
- [License](#license)

---

## Code of Conduct
We adopt the [Contributor Covenant](https://www.contributor-covenant.org/). Be respectful, professional and empathetic. Serious incidents can be reported through *GitHub Security Advisories* or directly to the project maintainers.

---

## Requirements
- **Docker Desktop** (or Podman) and **Docker Compose**.
- **Git**.
- (Optional) PHP 8.3 + Composer if you want to run outside containers.

> Minimum service versions: MySQL 8, Redis 7, PHP 8.3. The environment is prepared to be brought up with `docker compose` without additional local installations.

---

## Local Environment Setup
Clone the repository and bring up the services:

```bash
# 1) Clone
git clone https://github.com/<your-user>/shortener.git
cd shortener

# 2) Environment
cp .env.example .env  # or use your local .env
# Adjust credentials and HMAC keys in .env (see ENV.md)

# 3) Bring up the stack
docker compose up -d

# 4) Migrate/seed (first time)
docker compose exec app php artisan migrate --seed

# 5) Run the test suite
docker compose exec app ./vendor/bin/phpunit -d memory_limit=512M
```

> **Note**: The project exposes `/health` and `/metrics`. Redis is used for queues and metrics (counters + histograms).

---

## Repository Structure
- `app/` (Laravel code + runtime and CI **Dockerfile**)
- `deploy/` (deployment templates: `docker-compose.prod.yml`, `shortener.env`)
- `.github/workflows/ci.yml` (pipeline for tests + build/push of image)
- `config/shortener.php` (custom configuration: HMAC, limits, k‑anon)
- `app/Services/` (`SlugService`, `MetricsContract`, `Metrics`)
- `app/Http/Controllers/Web/RedirectController.php` (HEAD‑safe logic, atomic counter, HMAC signature)
- `routes/`, `resources/views/`, `database/`, `tests/`

---

## Git Workflow
- Main branch: **main** (protected).
- Create one branch per change:
  - `feat/<short-description>`
  - `fix/<short-description>`
  - `chore/<short-description>`
  - `docs/<short-description>`

Example:
```bash
git checkout -b feat/graceful-max-clicks-limit
# ... changes ...
git add .
git commit -m "feat: enforce atomic max_clicks with 404 when exceeded"
git push -u origin feat/graceful-max-clicks-limit
# Open a Pull Request to main
```

> **Do not** push directly to `main`. Every change must go through a PR and CI.

---

## Style and Quality
- **PSR-12**. Keep functions small, names clear and error handling explicit.
- Aligned with Laravel conventions (controller, service and job names).
- **Formatting**: if you use *laravel/pint* locally, run `./vendor/bin/pint` before committing (optional but recommended).
- **Blade**: avoid complex logic in views; delegate to controllers/services.
- **Security**: never expose secrets in logs or exceptions. Use `config()` / ENV.

---

## Tests
Run the full suite:

```bash
docker compose exec app ./vendor/bin/phpunit -d memory_limit=512M
```

Recommended coverage: **≥ 80%** for new features (not blocking, but appreciated).

Pay special attention to:
- **SlugService**: generation/parsing, HMAC signature.
- **RedirectController**: HEAD‑safe (does not consume click), expiration, *ban*, invalid signature, atomic click limit.
- **API** (create/show/ban/delete): rate limits, allowed domains/schemes.
- **SecurityHeaders** of the panel.
- **Metrics**: counters and histograms (at least smoke tests on critical routes).

If you introduce new endpoints or logic, add equivalent tests. Tests **must not** depend on external networks.

---

## Commits and Messages
Use **Conventional Commits**:

- `feat: …` new functionality
- `fix: …` bug fix
- `docs: …` documentation (README, ENV.md, etc.)
- `test: …` tests
- `refactor: …` refactor without functional changes
- `ci: …` pipeline/actions changes
- `chore: …` general tasks (dependencies, scripts)

Examples:
```
feat: add k-anon aggregation and endpoint for stats
fix: avoid null HMAC key on CI by loading from config
ci: buildx with gha cache and dockerhub push
docs: add DEPLOYMENT and OBSERVABILITY guides
```

Small, atomic commits. Messages in **imperative mood** and with context.

---

## Pull Requests
Criteria for opening a PR:
- Green tests locally.
- Update documentation if variables, endpoints or security change.
- Describe **what**, **why** and **how** to test. If applicable, add a screenshot or test logs.

**PR Checklist**:
- [ ] Focused changes (one topic per PR).
- [ ] No secrets or credentials.
- [ ] `ENV.md`/`OBSERVABILITY.md` updated if applicable.
- [ ] PHPUnit suite green.
- [ ] Edge cases reviewed (signature errors, expiration, limits, queue down).

Short template for the description:
```
### Summary
Briefly describe the change.

### Motivation
Why is it needed? What problem does it solve?

### How to test
Steps, commands and test data.

### Risks
Impact on security, latency, storage or compatibility.
```

---

## CI/CD
- The `.github/workflows/ci.yml` action:
  1. Runs PHPUnit (environment `app/`).
  2. Builds & pushes the Docker image (`latest`) to your registry.
- Configure in GitHub **Settings ▸ Secrets and variables ▸ Actions**:
  - `DOCKERHUB_USERNAME` (or equivalent variables for your registry),
  - `DOCKERHUB_REPO` (e.g. `your_user/shortener`),
  - `DOCKERHUB_TOKEN` (access token).

Typical errors and remedies are documented in `DEPLOYMENT.md`.

---

## Documentation
If your PR affects:
- Environment variables → update **ENV.md** (+ examples).
- Observability → update **OBSERVABILITY.md** (metrics, dashboards).
- Overview/usage → update **README.md**.

Add entries to **CHANGELOG.md** under `[Unreleased]` following *Keep a Changelog*.

---

## Security
- **Keys and secrets**: never in commits. Use ENV and the system's secret store (Docker, CI).
- **HMAC**: `SHORTENER_HMAC_KEY` must be **secret** and have enough entropy/Base64.
- **Rate limits**: keep reasonable limits to prevent abuse (`SHORTENER_MAX_*`).
- **Headers**: do not relax them without justification. Panel with CSP, HSTS, X‑Frame‑Options, etc.
- **Logs**: do not log sensitive data (full URLs if they contain secrets, IPs unnecessarily, etc.).
- **Queues**: if they go down, the redirect must remain safe (loss of analytics, never of security).

Changes that affect security require a **Risks** section in the PR and specific tests.

---

## Vulnerability Reporting
Do not open public issues for vulnerabilities. Use **GitHub Security Advisories** or contact the maintainers. Provide details and reproduction steps if possible.

---

## License
By contributing, you agree that your contribution is licensed under the project license indicated in `LICENSE`.
