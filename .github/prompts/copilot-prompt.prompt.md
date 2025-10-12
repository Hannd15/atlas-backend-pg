---
mode: agent
---
# Project Context: Laravel 12

Copilot must follow these conventions.

---

## Core Goals
- Controllers remain thin. All business logic lives in Service classes.
- Read existing route files for pattern reference.
- Always inspect and use existing Eloquent models before manipulating data.
- Enforce best practices for PHP 8.
- Communication occurs via JSON bodies.
- Preserve architectural consistency, reusability, and security.

---

## Backend — Laravel 12 (PHP 8)
- Controllers: validation + response only, always use OpenAPI annotations to document endpoints.
- Services: encapsulate business logic, injected through constructors.
- Models: single source of truth for data and relationships.
- Never place direct queries inside controllers.
- Do NOT make custom mappers; use Eloquent relationships and accessors.
- DO NOT use facades in services; always prefer dependency injection.
- Follow PSR-12 and clear PHPDoc.
- Prefer dependency injection and SOLID principles.

**Coding style**
- 4-space indent  
- K&R braces  
- camelCase → methods PascalCase → classes snake_case → DB columns

---


## Mandatory Checks
- Backend suggestions must check existing models before CRUD.
- All code must compile/lint cleanly (`tsc --noEmit`, PSR-12).
- Use PHPDoc and JSDoc for clarity.
- Suggested code must be testable (PHPUnit / Jest).

---

## Avoid
- Mixing controller and service responsibilities.
- Unvalidated or unchecked data.
- Hard-coded permissions or routes.
- Inline SQL or unscoped queries.

---

## Output Preference
Copilot should propose concise, complete, and standards-compliant code following the above conventions.
