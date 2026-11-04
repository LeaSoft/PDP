# Google sign-in

PDP lets people in with their work Google account. Only accounts managed by the
company Google Workspace get in: the server checks, on every sign-in, that Google
reports `email_verified = true`, that the Workspace domain (`hd`) is one of
`COMPANY_EMAIL_DOMAINS`, and that the email is on one of those domains. Personal
Gmail accounts, and personal Google accounts registered on a company address, are
rejected. The first sign-in creates an account with no roles; roles are still
granted in `/admin/users`.

An existing PDP account with the same email is linked on the first Google sign-in
and keeps all its PDPs, mentors, levels and notifications. At that moment its other
sessions and remember-me cookies stop working, and its email becomes read-only in
the profile.

Two-factor authentication in PDP is switched off: the second factor is Google's
2-Step Verification. Ask IT to enforce it in the Workspace admin console.

## 1. Create the OAuth client (Google Cloud Console)

1. Open https://console.cloud.google.com/ with a company account and pick (or create)
   a project for PDP.
2. **APIs & Services → OAuth consent screen**: user type **Internal**. Google itself
   then refuses accounts outside the organisation. App name `PDP`, support email, and
   the scopes `openid`, `email`, `profile`.
3. **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   type **Web application**, with these **Authorized redirect URIs**:
   - `http://localhost:8080/auth/google/callback` (local Docker)
   - `https://pdp.leasoft.co/auth/google/callback` (production)
4. Copy the **Client ID** and **Client secret**. Keep the secret out of the repository,
   chats and GitHub Secrets: it only goes into `.env` files.

## 2. Local setup

In `.env`:

```dotenv
COMPANY_EMAIL_DOMAINS=leasoft.org
AUTH_PASSWORD_LOGIN=true
GOOGLE_CLIENT_ID=<client id>
GOOGLE_CLIENT_SECRET=<client secret>
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback
```

Then run `docker compose exec php php artisan migrate` and open
http://localhost:8080/login. The "Continue with Google" button appears only when all
three `GOOGLE_*` values are set.

## 3. Production rollout

The deploy workflow (`.github/workflows/deployment.yaml`) does not run migrations and
does not template `.env`. The server's `/pdp/PDP/.env` is mounted into the `php`
container, so the steps below are manual.

**Stage 1. Deploy with both ways in.** Nothing changes for people until the Google
keys are in `.env`.

1. Merge to `main`; the workflow deploys.
2. Right after the deploy, on the server:

   ```bash
   cd /pdp/PDP
   docker compose -f docker-compose.prod.yaml exec php php artisan migrate --force
   ```

   Without this migration `/admin/users` and Google sign-in fail on the missing
   `users.google_id` column.
3. Add to `/pdp/PDP/.env`:

   ```dotenv
   COMPANY_EMAIL_DOMAINS=leasoft.org
   AUTH_PASSWORD_LOGIN=true
   GOOGLE_CLIENT_ID=<client id>
   GOOGLE_CLIENT_SECRET=<client secret>
   GOOGLE_REDIRECT_URI=https://pdp.leasoft.co/auth/google/callback
   ```

   Then restart the container so it reads the new values:
   `docker compose -f docker-compose.prod.yaml up -d --force-recreate php`.

**Stage 2. Everyone links Google.**

1. In `/admin/users`, compare each person's email with their real Google Workspace
   address. If someone has a personal address or an old alias, change it to their
   Google address (the person can do it in profile settings) **before** they sign in
   with Google. Otherwise Google creates a second, empty account. If that happens,
   delete the empty duplicate (phpMyAdmin) and fix the email on the old account.
2. Everyone signs in once with "Continue with Google". The **Google** column in
   `/admin/users` shows ✓ for linked accounts.

**Stage 3. Switch to Google-only** when every active user shows ✓.

1. Set `AUTH_PASSWORD_LOGIN=false` in `/pdp/PDP/.env` and recreate the `php`
   container as above. Password login, registration, password reset, the Password
   settings page and account deletion are closed.
2. Sign everyone out once, so old sessions and remember-me cookies (they last for
   months) stop working:

   ```bash
   docker compose -f docker-compose.prod.yaml exec php php artisan pdp:logout-everyone --force
   ```

From then on, when IT suspends someone's Google account, they can no longer sign in,
and their open PDP session ends at most `SESSION_LIFETIME` (120) minutes after their
last action.

**Rollback / emergency access:** set `AUTH_PASSWORD_LOGIN=true` and recreate the
container. Passwords are kept in the database.

## Limitations

- Accounts are matched by Google's account id (`sub`) first, then by email. A PDP
  email that doesn't match the Google address creates a separate account (see Stage 2).
- With password login off, nobody can delete their own account; an administrator
  does it in the database.
- There is no instant block for a person who has left; their session runs out on its
  own (see Stage 3).
