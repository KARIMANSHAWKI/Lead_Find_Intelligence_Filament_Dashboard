# Organization Tenancy Conversion

Converted the completed Laravel L1–L10 application from user-owned business data to lightweight organization ownership. No tenancy package, tenant database, domain/subdomain routing, roles, invitations, or billing was introduced.

## Final ownership model

```text
Organization
├── Users                  hasMany; each User belongsTo one Organization
├── IcpConfiguration       hasOne; configuration belongsTo Organization
├── AgentRuns              hasMany; run belongsTo Organization
└── Prospects              hasMany; prospect belongsTo Organization and AgentRun
    └── BuyingSignals      hasMany; signal belongsTo Prospect
```

`organization_id` is required and indexed on users and business records. ICP uses a unique organization index, enforcing at most one configuration per organization. Business tables no longer contain `user_id`. Authentication sessions retain their legitimate user association.

BuyingSignal has no organization column; its tenant is inherited through Prospect. User deletion retains shared organization business data. Organization deletion cascades to its users, ICP, runs, prospects, and signals. Run/prospect deletion retains the existing child cascade behavior.

## Filament isolation

The application still uses User + web guard and the `/app` panel. Navigation remains exactly Overview, ICP Configuration, Prospects, and Agent Runs.

- ICP loading and saving resolve the authenticated user's organization and use its `icpConfiguration()` relationship. `updateOrCreate` updates the shared configuration rather than creating a configuration per user.
- Prospect and Agent Run tables constrain queries by the authenticated user's `organization_id` before search or filtering.
- Agent Run filter options and prospect status options are generated only from the current organization.
- Both detail pages apply the same organization constraint on record resolution; cross-organization URLs return 404. Locked Livewire record IDs remain in place.
- Related run prospects constrain both organization and run ID. Signals are loaded through the already scoped prospect.
- Dashboard metrics, score distribution, opportunities, and activity use the current organization. Same-organization users see the same records and metrics.
- Organization IDs are excluded from editable forms, model mass assignment, and accepted external persistence fields. ICP save whitelists its business fields, so manipulated record/organization/user IDs are ignored.

No new update/delete UI was added for generated prospect intelligence or run history.

## Agent execution and ownership invariants

The already implemented FastAPI integration now resolves User → Organization → shared ICP, then creates AgentRun and Prospect through organization relationships. The existing request contract, HTTP validation, transaction boundary, failure behavior, and Laravel scoring remain unchanged. FastAPI receives no Laravel tenant-selection responsibility or organization ID.

Prospect validates that its organization matches its AgentRun when created or when either ownership association changes. Invalid assignments raise a validation error. An existing run cannot move to another organization while retaining differently owned prospects. Ordinary score/content updates avoid an unnecessary ownership lookup.

The final schema uses ordinary organization and run foreign keys. The previous user/run composite foreign key was removed; no new composite tenancy infrastructure was added. Trusted application code must preserve model validation rather than bypass it with raw ownership updates.

## Migration strategy

Created two forward migrations; historical L1–L4 migrations were left unchanged:

1. `2026_10_03_140404_create_organizations_table.php`: minimal organizations table with ID, name, and timestamps.
2. `2026_10_03_140405_move_business_ownership_to_organizations.php`: adds the user organization relationship, backfills one distinct organization per existing user, converts existing business ownership to organization IDs, removes old constraints/indexes, adds organization constraints/indexes, and requires user organization membership.

Each legacy user's ICP, runs, and prospects move into that user's distinct organization. Existing users are not silently merged. Existing record IDs, intelligence, scores, lifecycle dates, and buying-signal links are preserved. No duplicate business ownership columns remain after migration.

The local runtime uses SQLite. Its database had no users or business records before migration; the migration created no synthetic business/demo records. Populated legacy fixtures were separately tested to prove preservation, including user IDs that do not equal newly generated organization IDs.

SQLite schema changes temporarily disable foreign keys outside a surrounding migration transaction so table reconstruction does not delete child records. The backfill of users/organizations runs in a transaction. Foreign-key checking is restored; migration tests verify integrity after both conversion and reversal.

Rollback restores the original user schema when each organization with business data has exactly one user. It refuses rollback before changing schema if business data belongs to a shared or ownerless organization: there is no unambiguous original user owner to restore. The organization-table migration can subsequently be reversed normally. This guard avoids silently assigning shared data to an arbitrary user.

Both forward migrations are applied locally, in batch 4. All nine migrations report **Ran**.

## Factories and account provisioning

- `OrganizationFactory` generates a minimal valid organization.
- `UserFactory` creates an organization by default; `for($organization)` shares an existing one.
- ICP and AgentRun factories create organization-owned records.
- ProspectFactory derives its organization from its AgentRun. `forOrganization($organization)` creates both associations consistently; `for($run)` inherits that run's organization.
- BuyingSignalFactory remains prospect-owned.

Requiring organization membership would otherwise break Filament's existing account-creation command. A small subclass, bound through AppServiceProvider, preserves `make:filament-user` and its aliases. It creates a real organization for the new account by default or accepts a trusted console `--organization=<id>` option. Organization/user creation is transactional. The existing Filament prompts and password hashing are reused. No additional login system or user-facing organization selector exists.

Examples:

```bash
# Existing prompts; creates an organization for this account.
docker compose exec -w /var/www/html php php artisan make:filament-user --panel=app

# Trusted local/admin provisioning into an existing organization.
docker compose exec -w /var/www/html php php artisan make:filament-user --panel=app --organization=1
```

Application code may also provision through `$organization->users()->create(...)`. Direct account creation without an organization is rejected by the required database column.

## Tests and quality checks

| Check | Result |
| --- | --- |
| Full regression suite | **121 passed, 824 assertions**, 9.23 seconds |
| Focused tenancy suite | **16 passed, 121 assertions** |
| Ownership migration suite | **3 passed, 66 assertions** |
| Core persistence suite | **12 passed, 57 assertions** |
| Pint `--dirty --format agent` on host | Passed; formatting corrections applied |
| Full Pint in PHP container | Passed |
| Frontend production build | Passed; Vite 8.3.2 |
| Vite manifest | All referenced assets exist, including the custom Filament theme |
| Composer validation | Valid |
| Route inspection | Existing eight panel routes retained |
| Migration status | All nine migrations applied |
| Git whitespace check | Passed for tracked changes |

Coverage verifies organization/user relationships, shared ICP loading/updating, unique ICP enforcement, manipulated ownership input, mandatory two-organization prospect/run/signal isolation in both directions, search/filter isolation, private filter options, dashboard aggregates, shared ICP agent execution, ignored external tenant IDs, deletion semantics, ownership mismatch rejection, locked prospect identity, valid factories, required account membership, and account-command compatibility.

Migration tests verify populated legacy data survives conversion and reversal, foreign-key integrity remains valid, final columns/indexes are correct, and ambiguous shared-data rollback is refused safely. Existing L1–L10 authentication, navigation, form validation, UI, scoring, and integration tests continue to pass. HTTP remains mocked; no live FastAPI call was made.

The existing optional Fontaine font-fallback notice and obsolete Compose version notice remain non-blocking. No dependency manifests, secrets, real environment values, or external service code were changed.

## Files created

Paths are relative to the Laravel root `app/`:

```text
app/Models/Organization.php
app/Console/Commands/MakeOrganizationUser.php
database/factories/OrganizationFactory.php
database/migrations/2026_10_03_140404_create_organizations_table.php
database/migrations/2026_10_03_140405_move_business_ownership_to_organizations.php
tests/Feature/OrganizationTenancyTest.php
tests/Feature/OrganizationOwnershipMigrationTest.php
```

This report is created at workspace root: `ORGANIZATION_TENANCY_REPORT.md`.

## Files modified

```text
app/AGENTS.md                              (workspace path; repository rules)
AGENT.md                                  (workspace path; product architecture rules)

app/Models/User.php                        (remaining paths relative to Laravel root)
app/Models/IcpConfiguration.php
app/Models/AgentRun.php
app/Models/Prospect.php
app/Providers/AppServiceProvider.php
app/Application/LeadIntelligence/RunLeadIntelligenceAction.php
app/Filament/DashboardData.php
app/Filament/Pages/IcpConfiguration.php
app/Filament/Pages/Overview.php
app/Filament/Pages/Prospects.php
app/Filament/Pages/AgentRuns.php
app/Filament/Pages/ViewProspect.php
app/Filament/Pages/ViewAgentRun.php
resources/views/filament/pages/overview.blade.php
database/factories/UserFactory.php
database/factories/IcpConfigurationFactory.php
database/factories/AgentRunFactory.php
database/factories/ProspectFactory.php
tests/Feature/CorePersistenceTest.php
tests/Feature/IcpConfigurationTest.php
tests/Feature/DashboardTest.php
tests/Feature/IntelligencePagesTest.php
tests/Feature/LeadScoringTest.php
tests/Feature/LeadIntelligenceIntegrationTest.php
```

Generated ignored production build assets were rebuilt, and the local database schema was migrated. BuyingSignal code/schema, authentication guard, navigation registration, and FastAPI HTTP/scoring contract were retained. The enclosing Git repository treats the Laravel application directory as untracked, so this inventory compares against the inspected implementation rather than a committed application baseline.

## Principal commands executed

```bash
docker compose exec -T -w /var/www/html php composer show --direct
docker compose exec -T -w /var/www/html php php artisan make:model Organization --factory --migration --no-interaction
docker compose exec -T -w /var/www/html php php artisan make:migration move_business_ownership_to_organizations --no-interaction
docker compose exec -T -w /var/www/html php php artisan make:test OrganizationTenancyTest --phpunit --no-interaction
docker compose exec -T -w /var/www/html php php artisan make:test OrganizationOwnershipMigrationTest --phpunit --no-interaction
docker compose exec -T -w /var/www/html php php artisan make:command MakeOrganizationUser --no-interaction
docker compose exec -T -w /var/www/html php php artisan db:show --counts --no-interaction
docker compose exec -T -w /var/www/html php php artisan migrate --no-interaction
docker compose exec -T -w /var/www/html php php artisan migrate:status --no-interaction
docker compose exec -T -w /var/www/html php php artisan route:list --path=app
docker compose exec -T -w /var/www/html php php artisan test --compact tests/Feature/CorePersistenceTest.php
docker compose exec -T -w /var/www/html php php artisan test --compact tests/Feature/OrganizationTenancyTest.php
docker compose exec -T -w /var/www/html php php artisan test --compact tests/Feature/OrganizationOwnershipMigrationTest.php
docker compose exec -T -w /var/www/html php php artisan test --compact
docker compose exec -T -w /var/www/html php vendor/bin/pint --format agent
docker compose exec -T -w /var/www/html php composer validate --no-check-publish
git status --short -- .
git diff --check -- .
```

From Laravel root: `vendor/bin/pint --dirty --format agent`, `npm run build`, and a Vite manifest asset-existence check. Read-only inspection covered rules, models, factories, migrations, all affected Filament pages, dashboard data, tests, routes, and existing integration/account command code.

## Deferred

Organization management UI, invitations, organization switching, roles/permissions, billing/subscriptions, tenant domains, tenancy middleware/packages, separate databases, queues, CRM, and outreach remain intentionally absent. This conversion stops at simple organization ownership and isolation.
