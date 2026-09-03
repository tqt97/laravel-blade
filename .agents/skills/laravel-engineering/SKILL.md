---
name: laravel-engineering
description: Implement, refactor, debug, or review PHP and Laravel application code, including controllers, actions, services, DTOs, Eloquent, validation, authorization, queues, cache, tests, and production correctness. Use for Laravel feature work and defect-first code review; use the architecture skill for system-level topology decisions.
---

# Laravel Engineering

Produce framework-native, maintainable Laravel code that preserves business rules and works with the application's actual PHP, Laravel, database, and package versions.

## Start with project evidence

Before proposing or changing code, inspect the relevant routes, requests, policies, models, casts, migrations, controllers, actions/services, resources, frontend contract, and tests. Check `composer.json`, lock files, configuration, and established conventions when compatibility matters. Do not invent tables, columns, relationships, package APIs, or domain rules.

## Choose the smallest useful boundary

- Keep controllers responsible for HTTP orchestration: authorize, accept validated input, invoke the use case, and return a response.
- Use a `FormRequest` when validation or request authorization is non-trivial or reused.
- Use an Action for a named application use case or mutation with a clear input and output.
- Use a Service for cohesive domain/infrastructure behavior shared by multiple use cases; do not create pass-through services.
- Introduce a DTO when data crosses a meaningful boundary, needs normalization/type safety, or prevents unstable array contracts.
- Use Eloquent/query objects directly when they remain clear. Add a Repository only when it provides a real abstraction such as multiple persistence backends, complex reusable persistence policy, or a test seam that cannot be achieved cleanly otherwise.
- Use API/Inertia Resources or explicit view models when serialization is a public contract; avoid leaking unrestricted models.

## Correctness rules

- Enforce authorization server-side and consider IDOR, mass assignment, guard mismatches, tenant scope, and privilege escalation.
- Put multi-write invariants inside a transaction. For concurrency-sensitive state, define the invariant first and choose a database constraint, atomic conditional update, lock, idempotency key, or retry deliberately.
- Store money as integer minor units or an explicitly chosen decimal representation; never silently use binary floating point.
- Treat queues as at-least-once delivery: make handlers idempotent, define retry/backoff behavior, and avoid dispatching externally visible work before a transaction commits.
- Prevent N+1 queries, unbounded reads, accidental lazy loading, unsafe pagination, and cache invalidation gaps.
- Make migrations safe for the actual database and deployment strategy. Preserve rollback and mixed-version deployment concerns where applicable.
- Keep secrets and sensitive data out of logs, exceptions, audit payloads, URLs, and client props.

## Review mode

Lead with concrete defects, ordered by severity. For each finding include the affected location, failure scenario, impact, and smallest sound fix. Distinguish confirmed defects from risks or optional improvements. Avoid unrelated modernization.

Check, as applicable: value/type semantics, nullability and enum casts, error handling, authorization, validation, transactions, concurrency, query count/plans, queue delivery, cache consistency, resource usage, compatibility, observability, and backward compatibility.

## Verification

Use the project's existing commands and configuration. Run the narrowest relevant tests first, then broader gates when justified. Typical checks include Pest/PHPUnit, Pint, PHPStan/Larastan, migration tests, database-specific integration tests, and frontend tests. Never claim a check passed unless it actually ran successfully; report skipped checks and blockers precisely.

When implementing, summarize the changed behavior, important trade-offs, tests run, and any residual risk.
