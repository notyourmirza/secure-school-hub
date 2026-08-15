## What changed

<!-- Short description of the change and why. -->

## Security checklist

- [ ] All SQL uses prepared statements with bound parameters (no string interpolation)
- [ ] Every query and write is scoped by `school_id` (tenant isolation)
- [ ] New endpoints/pages call `require_permission()` / `Auth::requirePermission()`
- [ ] State-changing HTML forms include the CSRF token; API writes require a bearer token
- [ ] All user-controlled output is escaped with `e()` before rendering
- [ ] Uploads validate MIME type, extension and size, and are stored outside the web root
- [ ] No secrets, credentials or `.env` files added
- [ ] Errors return generic messages; details go to the server log only

## Database

- [ ] Schema changes ship as a new numbered migration in `website/migrations/`
- [ ] Migration is idempotent and was verified with `php website/tools/migrate.php --dry-run`
