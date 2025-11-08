---
mode: agent
---
# Project Context: Laravel 12

You must follow these conventions.

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
1. Endpoint Naming and Structure:

Resource-Based: Endpoints should be plural nouns that represent the resources being managed (e.g., /users, /permissions, /discounts). These endpoints must include the relationships of the resource but parsed into a single comma separated string that includes the names (or the equivalent of a name) of the relations, if confused, skip and ask for clarification at the end (when asking for clarification present the relevant information and models).

Hierarchical Structure: For nested resources, use a hierarchical path (e.g., /express-projects/{id}/steps, /quotes/{id}/tissues/{tissueId}).

Specific Actions: Use verbs for endpoints that perform specific actions that don't map directly to a CRUD operation on a single resource (e.g., /files/upload-url, /users/{id}/status).

Dropdowns: For endpoints that provide data formatted for UI dropdowns, use the /{resource}/dropdown pattern (e.g., /coccidiosis-program/dropdown).

2. HTTP Methods:

GET: For retrieving resources.

POST: For creating new resources.

PATCH: For updates to existing resources.

DELETE: For deleting resources.

never use PUT.

3. Request and Response Bodies (JSON):

POST and PATCH Requests: The request body should be a JSON object containing the properties of the resource to be created or updated.

Successful Responses (200 OK, 201 Created):

For GET requests that return a list, the response should be a JSON array of objects.

For GET requests that return a single object, the response should be a single JSON object.

For POST, PATCH, and DELETE operations that are successful, the response should be a JSON object with a message property confirming the action (e.g., {"message": "Permission created successfully"}).

Error Responses (4xx, 5xx):

Error responses must return a JSON object.

This object should contain an error property with a concise error message. A message property with a more detailed explanation can also be included where appropriate (e.g., {"error": "Invalid file path", "message": "The provided file path is invalid"}).

4. Parameters:

Path Parameters: Use path parameters for identifying specific resources (e.g., /permissions/{id}).

5. Common Schemas and Data Formats:
Dropdown Data: Endpoints designed for dropdowns should return an array of objects, each with value and label properties, the value being the id and the label being the name or the equivalent of a name for the related resource.


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
