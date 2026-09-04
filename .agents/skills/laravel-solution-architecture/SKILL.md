---
name: laravel-solution-architecture
description: Design or review Laravel system architecture, module boundaries, data ownership, APIs, queues, events, caches, scalability, reliability, security, observability, deployment, ADRs, and migration plans. Use for cross-module or system-level decisions rather than ordinary CRUD implementation.
---

# Laravel Solution Architecture

Turn business goals and quality attributes into an architecture that can be operated, evolved, and verified. Prefer an incremental modular monolith unless evidence justifies distributed-system cost.

## Establish the decision context

Identify actors, critical journeys, data sensitivity, invariants, traffic shape, latency/availability targets, consistency needs, failure tolerance, recovery objectives, team ownership, budget, deployment constraints, and current system limits. Mark assumptions and ask only for missing facts that could change the decision materially.

## Design from invariants and ownership

- Define module/bounded-context responsibilities and the authoritative owner of each datum.
- Separate synchronous work required for the user-visible invariant from asynchronous side effects.
- Specify transaction boundaries, duplicate delivery behavior, ordering needs, idempotency, retries, dead letters, and reconciliation.
- Choose cache keys, TTL, invalidation, stampede protection, and acceptable staleness explicitly.
- For integrations, define timeouts, retry eligibility, rate limits, circuit behavior, contract versioning, and failure visibility.
- Evaluate database constraints, indexes, query plans, partitioning/archival, connection limits, backups, restore drills, and zero-downtime migrations.
- Include authentication, authorization, tenant isolation, encryption, secret handling, auditability, abuse controls, and privacy retention.
- Define logs, metrics, traces, correlation IDs, SLO indicators, alerts, dashboards, and operational runbooks.

## Avoid premature distribution

Recommend microservices only when independently owned boundaries, scaling isolation, release independence, regulatory isolation, or fault containment justify network, consistency, observability, and operational costs. Otherwise show how modules, queues, outbox patterns, and stable contracts provide an evolutionary path inside Laravel.

## Deliverables

Adapt the detail to the request, using:

- Context and assumptions.
- Functional and quality requirements.
- Proposed components, ownership, data flow, and trust boundaries.
- Capacity estimates with stated inputs rather than unexplained numbers.
- Failure modes and recovery behavior.
- Alternatives and trade-offs.
- Phased migration with rollback and compatibility strategy.
- Verification plan: load, failure, concurrency, security, restore, and observability tests.
- ADR using `Context`, `Decision drivers`, `Options`, `Decision`, `Consequences`, and `Follow-up` when a durable decision is needed.

Do not present estimates, guarantees, or technology choices as facts without evidence.
