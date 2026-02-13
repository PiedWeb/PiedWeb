# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Working Guidelines

### Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it — don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

### Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

## Project Overview

PHP monorepo managed with Symplify MonorepoBuilder. Contains packages for web scraping, crawling, and data extraction. Requires PHP >= 8.4.

## Commands

```bash
composer test                  # Run all tests (excludes google suite)
composer testf {filter}        # Run specific test by filter name
composer test-google           # Run google package tests (separate, can timeout)
composer stan                  # PHPStan static analysis (level: max)
composer format                # php-cs-fixer
composer rector                # Rector + auto-format
composer validate-monorepo     # Validate monorepo structure
```

After changes: `composer test && composer stan && composer format`

## Architecture

### Monorepo Structure

Each package lives in `packages/{name}/` with its own `src/` and `tests/` directories. On push to `main`, GitHub Actions splits each package to its own repo under the `piedweb/` org.

### Packages and Dependencies

- **curl** — OOP curl wrapper (uses curl-impersonate-php). Foundation for network packages.
- **extractor** — HTML/DOM data extraction (links, metadata, canonical, hreflang, robots, indexability). Depends on curl.
- **google** — Unofficial Google SERP/Suggests extraction via Puppeteer or curl. Depends on curl. Requires Node.js + Puppeteer (`npm install`).
- **crawler** — SEO web crawler CLI (Symfony Console). CSV export, PageRank, robots.txt. Depends on curl, extractor.
- **text-analyzer** — Semantic text analysis, expression harvesting, density.
- **rison** — URI-optimized JSON encoder/decoder (Rison format).
- **composer-symlink** — Symlinks duplicate composer dependencies to save disk space.
- **render-html-attribute** — Twig extension for HTML attribute rendering (`attr()`, `mergeAttr()`).
- **method-doc-block-generator** — Generates method documentation blocks.

### Namespaces

- Source: `PiedWeb\{PackageName}\` → `packages/{name}/src/`
- Tests: `PiedWeb\{PackageName}\Test\` → `packages/{name}/tests/`

## Code Standards

- **PHPStan level max** — all code must pass strictest static analysis
- **php-cs-fixer** — PSR-12 + Symfony ruleset with risky rules, short array syntax, ordered imports, trailing commas in multiline, blank lines before return/throw/try/break/continue/declare
- **Rector** — PHP 8.0/8.1 level sets, code quality, coding style, early return, type declarations
- Style: camelCase methods, PascalCase classes, UPPER_SNAKE constants, short `[]` arrays
- `not_operator_with_successor_space`: `! $foo` (space after `!`)

## Testing

- PHPUnit 12 with testdox output
- Default suite runs all packages except google (google tests hit real APIs and can timeout)
- Some tests are integration tests calling real URLs
- Test naming: `{Feature}Test` class, `test{Feature}()` methods
