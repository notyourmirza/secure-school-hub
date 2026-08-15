# Security Policy

## Supported versions

Only the `main` branch receives security fixes.

## Reporting a vulnerability

Report privately through GitHub's **Report a vulnerability** button (Security tab)
or by email to the maintainers. Please do not open a public issue.

Include: affected component (`website/`, `android/`, `windows/`), reproduction
steps, impact, and any logs with secrets removed. We acknowledge reports within
3 business days and aim to ship a fix or mitigation within 30 days.

## Scope

| Component | Notes |
| --- | --- |
| `website/` | PHP API + admin panel, MySQL schema and migrations |
| `android/` | Kotlin client, offline sync, token storage |
| `windows/` | .NET desktop client, offline queue |

Out of scope: findings that require a compromised device or a self-hosted
misconfiguration (for example running the API over plain HTTP in production,
which the clients already refuse).

## Handling secrets

Never commit `.env` files, real credentials, or production database dumps. CI
fails the build when a credential-shaped literal or environment file is
committed. Rotate any secret that reaches a commit, even after a force-push.
