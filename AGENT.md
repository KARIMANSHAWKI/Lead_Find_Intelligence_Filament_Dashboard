# Lead Intelligence Platform — Laravel / Filament

## Project Goal

Build the Laravel application for a Lead Intelligence platform.

The Laravel application is the main product interface and system of record.

It is responsible for:

- authentication
- organization ownership and ICP configuration
- ICP configuration
- agent run management
- prospect persistence
- buying signal persistence
- deterministic lead scoring
- dashboard analytics
- Filament administration/product UI
- communication with the separate FastAPI Lead Intelligence service

The core product value proposition is:

> Find companies worth contacting now — and explain WHY NOW.

The Laravel application must present this intelligence clearly and make the agent workflow easy to understand.

---

# System Architecture

The system is split into two applications:

```text
Filament Dashboard
        ↓
Laravel Application
        ↓ HTTP
FastAPI Lead Intelligence Service
        ↓
AI / Search / Research Providers
```

Laravel is the system of record.

FastAPI is the intelligence service.

Laravel must NOT contain LLM prompts, company-search provider logic, or web-research implementation.

FastAPI must NOT directly access Laravel's database.

Communication happens through HTTP APIs.

---

# Laravel Responsibilities

Laravel owns:

```text
Authentication
Users
Organizations
ICP Configuration
Agent Runs
Prospects
Buying Signals
Lead Scores
Dashboard
Filament UI
Persistence
Business Rules
FastAPI HTTP Integration
```

Laravel must calculate the authoritative final numeric lead score.

---

# FastAPI Responsibilities

FastAPI owns:

```text
Company discovery
Provider selection
Web research
Evidence collection
LLM reasoning
ICP-fit classification
Product-relevance classification
Buying-signal interpretation
WHY NOW generation
Structured prospect intelligence
```

Laravel should treat FastAPI as an external service.

---

# Hackathon Constraints

This is a hackathon MVP.

Prioritize:

1. polished working demo
2. simple understandable architecture
3. reliable end-to-end flow
4. strong visual presentation
5. clear WHY NOW explanations
6. explainable scoring
7. fast implementation

Avoid unnecessary enterprise architecture.

Do not add abstractions unless they protect a real boundary.

A working simple implementation is better than an incomplete sophisticated implementation.

---

# Product Navigation

The MVP navigation should remain small.

Preferred navigation:

```text
Overview
ICP Configuration
Prospects
Agent Runs
```

Do not create unnecessary sections.

---

# Dashboard Goal

The dashboard should communicate the value of the agent immediately.

The user should quickly understand:

```text
How many opportunities were found?
Which leads are strongest?
Why are they worth contacting now?
What has the agent been doing?
```

Preferred dashboard content:

```text
Total Prospects
High Priority
Medium Priority
Agent Runs

Opportunity Score Distribution

Top Opportunities

Recent Agent Activity
```

---

# Visual Direction

The application should feel like a modern B2B AI SaaS product.

Use:

- clean spacing
- professional typography
- subtle borders
- rounded cards
- restrained shadows
- clear hierarchy
- consistent badge styles
- readable tables
- responsive layouts
- polished empty states
- clear loading states

Avoid:

- excessive gradients
- excessive animation
- neon-heavy UI
- childish visuals
- overly large cards
- clutter
- unnecessary decorative elements

The product should look suitable for a startup demo and real business users.

---

# Filament Rules

Use Filament as the main application UI.

Filament is responsible for:

- pages
- resources
- tables
- forms
- actions
- widgets
- notifications

Keep Filament code focused on presentation and interaction.

Do NOT put important business logic directly inside:

```text
Filament Pages
Filament Resources
Table column callbacks
Form callbacks
Dashboard Widgets
```

Delegate business operations to application/service classes.

---

# Preferred Laravel Architecture

Use pragmatic layered architecture.

Conceptually:

```text
app/
├── Application/
│   ├── Actions/
│   ├── DTOs/
│   ├── LeadIntelligence/
│   └── Scoring/
│
├── Domain/
│   ├── Organizations/
│   ├── Prospects/
│   └── AgentRuns/
│
├── Infrastructure/
│   └── LeadIntelligence/
│
├── Filament/
│   ├── Pages/
│   ├── Resources/
│   └── Widgets/
│
└── Models/
```

Do NOT create all folders immediately.

Create folders/classes only when the current milestone needs them.

---

# Dependency Direction

Prefer:

```text
Filament
   ↓
Application
   ↓
Domain

Infrastructure
   ↑
implements external boundaries
```

The UI should call application actions/services.

Application code may coordinate models and infrastructure.

Infrastructure should contain external HTTP integrations.

---

# Models

The MVP will eventually contain models such as:

```text
Organization
User
IcpConfiguration
AgentRun
Prospect
BuyingSignal
```

Exact schema should be introduced incrementally through milestones.

Do not create every model before it is needed.

---

# Organization Ownership

Every User belongs to exactly one Organization. An Organization has many Users, one active ICP configuration, many AgentRuns, and many Prospects.

`organization_id` is the tenant boundary on Users, IcpConfiguration, AgentRun, and Prospect. Do not retain duplicate `user_id` business ownership. BuyingSignal inherits its organization through Prospect and does not need its own organization column.

All business reads, mutations, dashboard aggregates, search/filter options, related records, and detail pages must be scoped to the authenticated user's organization. Same-organization users share data. Cross-organization record access must be blocked server-side, including direct URLs and manipulated Livewire state.

Organization IDs must never be editable tenant business fields or trusted from external responses. Platform-admin account provisioning may explicitly select the organization for a new ordinary user. Resolve ICP through the authenticated user's Organization and enforce one ICP per organization. A Prospect and its AgentRun must have the same organization.

Existing FastAPI integration resolves User → Organization → organization ICP, creates an organization-owned run, and persists its prospects in that organization. FastAPI remains stateless about Laravel tenancy.

Create accounts through organization relationships. The existing `make:filament-user` console command creates an organization by default, or accepts a trusted `--organization` option for an existing organization. This is account provisioning, not an invitation or registration UI.

The user separately authorized a landlord panel at `/landlord` for listing all Users and Organizations and creating organizations and application users. Only protected `is_platform_admin` accounts may access these directories. This metadata directory and its authorized account provisioning actions are the sole scope exceptions; tenant business records and the `/app` dashboard remain organization-scoped, including for platform administrators. Grant platform-admin access only through trusted console provisioning; do not add RBAC infrastructure or editable privilege fields. Only platform admins may select an organization while creating an ordinary user, and creation actions must recheck authorization server-side. Passwords must be confirmed and hashed; never mass-assign privilege flags from these forms.

Use simple Eloquent relationships and query constraints. Do not introduce tenancy packages, separate databases, domains/subdomains, complex tenant middleware, invitations, roles, billing, or subscriptions.

---

# ICP Configuration

The Ideal Customer Profile should eventually include:

```text
product
target industries
location
minimum company size
maximum company size
ideal customer description
```

Laravel owns this organization-level configuration. One organization has at most one active ICP configuration.

Laravel sends this context to FastAPI when starting a lead-intelligence run.

---

# Agent Runs

An AgentRun represents one execution of the Lead Intelligence workflow.

Conceptually:

```text
User clicks Find Opportunities
        ↓
Laravel creates AgentRun for the authenticated user's organization
        ↓
Laravel calls FastAPI
        ↓
FastAPI returns prospects
        ↓
Laravel persists results
        ↓
AgentRun becomes completed
```

Suggested statuses:

```text
pending
running
completed
failed
```

Do not implement a complex state machine.

---

# Prospects

A Prospect represents a qualified company returned by the intelligence service.

Potential fields include:

```text
company_name
website
icp_fit
product_relevance
evidence_quality
why_now
score
status
agent_run_id
organization_id
```

Exact fields should be introduced in the appropriate milestone.

---

# Buying Signals

A Prospect may have multiple BuyingSignals.

A signal should contain structured evidence.

Conceptually:

```text
type
evidence
source_url
strength
```

Relationship:

```text
Prospect
    hasMany
BuyingSignal
```

Do not persist unsupported or fabricated signals.

Laravel trusts only validated FastAPI responses.

---

# WHY NOW

`why_now` is one of the most important fields in the product.

The UI should make it prominent.

A prospect detail page should make it easy to understand:

```text
Company
Score
ICP Fit
Product Relevance

WHY NOW

Buying Signals

Evidence Sources
```

Do not hide WHY NOW inside a small table cell on the detail page.

---

# Lead Scoring

FastAPI must NOT determine the authoritative numeric score.

Laravel owns deterministic scoring.

Target score:

```text
ICP Match            40
Buying Signals       30
Product Relevance    20
Evidence Quality     10
                    ---
                    100
```

The exact mappings will be implemented in a later milestone.

Do not ask the LLM to generate a 0–100 score.

---

# Scoring Architecture

Scoring logic should live in a focused class.

Preferred concept:

```text
Application/Scoring/LeadScoringService
```

or another clear equivalent.

Do NOT place authoritative scoring logic inside:

```text
Prospect model accessor
Filament table
Filament widget
FastAPI
Blade view
```

---

# FastAPI Integration

Laravel will eventually call:

```text
POST /api/v1/agent/run
```

through an infrastructure client.

Preferred concept:

```text
Infrastructure/LeadIntelligence/
    FastApiLeadIntelligenceClient.php
```

The exact name may follow repository conventions.

Do not make HTTP calls directly from Filament pages/resources.

---

# FastAPI Request Contract

Expected request shape:

```json
{
  "run_id": 1,
  "client": {
    "product": "Recruitment Management Software",
    "target_industries": [
      "Software",
      "Logistics"
    ],
    "location": "Egypt",
    "company_size": {
      "min": 50,
      "max": 300
    },
    "ideal_customer_description": "Companies actively growing their teams"
  }
}
```

Laravel creates the `run_id`.

FastAPI returns the same `run_id`.

---

# FastAPI Response Concept

Expected response shape:

```json
{
  "run_id": 1,
  "prospects": [
    {
      "company_name": "ABC Logistics",
      "website": "https://example.com",
      "icp_fit": "high",
      "product_relevance": "high",
      "evidence_quality": "high",
      "why_now": "The company is actively hiring and expanding its operations.",
      "buying_signals": [
        {
          "type": "hiring_growth",
          "evidence": "The company currently lists multiple active job openings.",
          "source_url": "https://example.com/jobs",
          "strength": "high"
        }
      ]
    }
  ]
}
```

Laravel must validate external response data before persistence.

The Run Lead Agent UI now uses an asynchronous result callback. Laravel adds
`callback: {url, token}` to the request. FastAPI acknowledges with HTTP 202 and
`{run_id, status: "running"}`, then posts the response above with `status: "completed"`
to `POST /api/v1/agent/runs/{run}/result`. Failed callbacks use `status: "failed"`
and a safe `error_code`, never raw exceptions. Callback authorization uses the
run-specific bearer token; Laravel stores only its hash. FastAPI accepts only the
configured trusted Laravel callback endpoint. Laravel persists validated results,
scores and a database notification atomically. `initiated_by_user_id` identifies
the notification recipient, while `organization_id` remains the ownership boundary.
Requests without a callback retain the synchronous FastAPI response for compatibility.


---

# HTTP Client Boundary

Use Laravel's HTTP client.

External HTTP calls belong behind a focused client/service.

For example:

```text
FastApiLeadIntelligenceClient
```

Its responsibilities may include:

```text
build request
send request
handle timeout
handle HTTP errors
validate basic response structure
return structured application data
```

Do not put unrelated scoring or persistence logic in the HTTP client.

---

# Configuration

All environment-specific values must come from configuration.

Future example:

```env
FASTAPI_BASE_URL=
```

Use Laravel configuration files such as:

```text
config/services.php
```

or a dedicated configuration file if useful.

Do not hardcode service URLs.

Do not commit secrets.

---

# Persistence Rules

Laravel owns PostgreSQL persistence.

FastAPI must never directly manipulate Laravel tables.

When processing an agent run:

```text
call FastAPI
    ↓
receive response
    ↓
validate response
    ↓
calculate score
    ↓
DB transaction
    ↓
persist prospects/signals
```

Do not keep a database transaction open while waiting for the HTTP request.

---

# Filament Overview Dashboard

The overview page should eventually contain cards such as:

```text
Total Prospects
High Priority
Medium Priority
Agent Runs
```

and sections such as:

```text
Opportunity Score Distribution
Top Opportunities
Recent Agent Activity
```

For early UI milestones, deterministic demo data is allowed.

Demo data must later be replaced with real database data.

---

# Prospect List

The eventual prospects table should prioritize useful sales information.

Preferred columns:

```text
Company
Score
ICP Fit
Product Relevance
Strongest Signal
Status
Created At
```

Useful filters may include:

```text
High Priority
Medium Priority
Low Priority
ICP Fit
Agent Run
Industry
```

Do not overcrowd the table.

---

# Prospect Detail

The prospect detail experience should be one of the strongest demo screens.

Example hierarchy:

```text
ABC Logistics

87 / 100
High Priority

ICP Fit
High

Product Relevance
High

WHY NOW
The company is actively hiring across several departments...

BUYING SIGNALS

Hiring Growth
18 active roles
Source →

Expansion
New Cairo office
Source →
```

Evidence links should be easily accessible.

---

# Agent Runs UI

Agent Runs should help users understand the AI workflow.

A run may eventually show:

```text
status
started time
completed time
prospects found
qualified prospects
failure message
```

Keep it simple for the MVP.

---

# Agent Activity

For the hackathon, agent activity may initially be derived from AgentRun and persisted results.

Do not build complicated real-time event streaming unless explicitly requested later.

Possible activity examples:

```text
Agent run started
12 candidates discovered
8 companies researched
5 opportunities qualified
Run completed
```

---

# Authentication

Use Laravel/Filament authentication conventions already present in the project.

Do not redesign authentication unless necessary.

Dashboard and business data should require authenticated access.

---

# Testing

Follow the repository's existing test framework and conventions.

Prefer feature tests for:

```text
Filament access
pages
actions
HTTP integration boundaries
persistence workflows
```

Prefer unit tests for:

```text
scoring
DTO mapping
small deterministic services
```

Do not call live FastAPI or external providers during normal unit tests.

Use Laravel HTTP fakes for FastAPI client tests.

---

# Error Handling

External failures should be handled clearly.

Examples:

```text
FastAPI unavailable
FastAPI timeout
invalid response
agent run failed
```

The UI should show concise business-friendly errors.

Do not expose:

```text
stack traces
secrets
API keys
raw authorization headers
```

---

# Logging

Log useful operational information.

Examples:

```text
agent run started
FastAPI request sent
FastAPI request failed
results received
prospects persisted
run completed
```

Do not log secrets.

---

# Coding Rules

Follow Laravel conventions.

Use:

- typed method signatures where practical
- clear class names
- small focused classes
- framework conventions
- dependency injection where useful

Avoid:

```text
HelperService
UtilityService
ManagerService
```

when a more descriptive name is possible.

Do not introduce repository classes for every Eloquent model unless there is a real need.

Do not implement CQRS, event sourcing, or complicated DDD infrastructure for this hackathon.

---

# Filament Coding Rules

Keep Filament pages/resources thin.

Good:

```text
Filament Action
    ↓
Application Action
    ↓
Business operation
```

Avoid:

```text
Filament Action
    ↓
150 lines of HTTP + persistence + scoring logic
```

---

# Current Implementation Priority

Build incrementally.

Current Laravel milestone order:

```text
L1 Laravel + Filament Foundation
        ↓
L2 Polished Dashboard with Demo Data
        ↓
L3 ICP Configuration UI
        ↓
L4 Core Database Models
        ↓
L5 Prospects UI
        ↓
L6 Prospect Detail
        ↓
L7 Agent Runs UI
        ↓
L8 Deterministic Scoring
        ↓
L9 FastAPI Integration
        ↓
L10 Demo Polish
```

Do not implement future milestones automatically.

At the end of every milestone:

1. run tests
2. run formatter/linter if configured
3. report created files
4. report modified files
5. report commands executed
6. report test results
7. explain important architectural decisions
8. stop and wait for the next milestone

---

# Milestone L1 — Foundation

L1 should implement only:

```text
Laravel project foundation
Filament panel
Overview dashboard
navigation
placeholder pages
Run Lead Agent placeholder action
FASTAPI_BASE_URL configuration placeholder
basic tests
```

Do not implement:

```text
FastAPI calls
real prospects
database business models
lead scoring
agent run persistence
buying signals
```

---

# Milestone L2 — Dashboard UI

L2 will focus on visual quality.

Use deterministic demo data.

Build:

```text
metric cards
score distribution
top opportunities
agent activity
responsive dashboard layout
empty/loading-friendly components
```

Do not connect FastAPI yet.

---

# Milestone L3 — ICP Configuration

L3 will implement the UI and persistence for:

```text
product
target industries
location
company size min/max
ideal customer description
```

---

# Milestone L4 — Core Persistence

L4 added core persistence; organization ownership is now introduced separately:

```text
Organization
AgentRun
Prospect
BuyingSignal
relationships
migrations
factories
```

---

# Milestone L5 — Prospects List

L5 will implement the polished prospects browsing experience.

---

# Milestone L6 — Prospect Detail

L6 will implement:

```text
score
WHY NOW
buying signals
evidence
qualification information
```

---

# Milestone L7 — Agent Runs

L7 will implement run history and status UI.

---

# Milestone L8 — Lead Scoring

L8 will implement deterministic 0–100 scoring in Laravel.

---

# Milestone L9 — FastAPI Integration

L9 will connect Laravel to:

```text
POST /api/v1/agent/run
```

and persist results.

---

# Milestone L10 — Demo Polish

L10 will focus on:

```text
loading states
notifications
empty states
responsive behavior
demo flow
visual polish
```

---

# Final Design Principle

Laravel is the product and persistence layer.

FastAPI is the intelligence engine.

Keep this boundary explicit:

```text
Laravel
→ knows business state

FastAPI
→ knows how to research and reason
```

Build the smallest clean architecture that preserves this separation and delivers a polished hackathon demo.