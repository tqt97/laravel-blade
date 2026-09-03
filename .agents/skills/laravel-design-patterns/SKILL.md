---
name: laravel-design-patterns
description: Select, explain, implement, or review design patterns in PHP and Laravel when behavior varies, dependencies need boundaries, workflows grow complex, or existing abstractions are unclear. Use to compare patterns and prevent over-engineering, not for routine Laravel code that is already simple.
---

# Laravel Design Patterns

Recommend a pattern only after identifying the concrete source of change, coupling, duplication, test difficulty, or invalid state. A pattern is a tool, not the goal.

## Decision method

1. State the current pressure in one sentence: what changes, how often, and what breaks when it changes.
2. Show the simplest viable design, including keeping the current code when appropriate.
3. Compare at most the genuinely relevant alternatives by complexity, extensibility, runtime cost, Laravel fit, testability, and migration effort.
4. Select a pattern only when its benefit exceeds the extra indirection.
5. Define deletion or simplification criteria so temporary abstractions do not become permanent ceremony.

## Useful mappings

- Strategy: interchangeable algorithms selected by explicit business context.
- Factory: construction varies or selecting the correct implementation should be centralized.
- Adapter: isolate a third-party or legacy contract from the application contract.
- Decorator: add composable behavior without growing conditionals or subclasses.
- State: valid behavior and transitions depend on an entity's lifecycle state.
- Command/Action: represent a use case or mutation with a clear boundary.
- Observer/listener: secondary reaction to an event; avoid hiding core invariants in observers.
- Specification: reusable, composable business predicates that have outgrown local scopes.
- Pipeline: ordered, independently testable processing steps.
- Repository: a meaningful persistence boundary, not a wrapper around every Eloquent call.

Prefer Laravel-native mechanisms—container bindings, contracts, events, jobs, middleware, policies, scopes, casts, and pipelines—when they express the design without custom infrastructure.

## Implementation constraints

- Keep domain naming visible; do not use generic names such as `Manager`, `Helper`, or `Processor` without a precise responsibility.
- Keep interface ownership on the consuming side when isolating infrastructure.
- Make strategy/factory selection exhaustive and fail clearly for unsupported values.
- Do not move validation, authorization, or transaction ownership into a pattern unless that boundary truly owns it.
- Preserve behavior with focused tests before refactoring risky legacy code.

Deliver a recommendation containing: problem, options, chosen design, class responsibilities, runtime flow, incremental migration, tests, and reasons rejected options are not suitable now.
