# Deployment Incident Report

Date: 2026-03-27
System: NFA Document Tracking System
Environment reviewed: migrated server deployment

## Reported symptoms

1. The login page loads on the server URL, but the NFA logo is broken.
2. After attempting to log in, the browser is redirected to `localhost/DTS/public/auth/login`.
3. The redirected `localhost` page cannot be reached from the deployed environment.

## Investigation summary

The two visible issues most likely share the same root cause: the application is building absolute URLs from `URLROOT`, and the runtime value of `URLROOT` is resolving to `localhost` on the server.

Evidence found in the codebase:

- `app/config/config.php:11-13` defines:
  - `$host = $_SERVER['HTTP_HOST'] ?? 'localhost';`
  - `define('URLROOT', $scheme . '://' . $host . '/DTS/public');`
- `app/views/auth/login.php:57` loads the logo from:
  - `<?php echo URLROOT; ?>/assets/logo-nfa-da.jpg`
- `app/controllers/Auth.php` redirects users with:
  - `header('Location: ' . URLROOT . '/auth/login');`
  - `header("Location: " . URLROOT . "/dashboard");`

This means:

- If `URLROOT` becomes `http://localhost/DTS/public`, the logo request also points to `localhost`, which explains the broken image.
- The same `URLROOT` is used for login redirects, which explains why authentication-related navigation also jumps to `localhost`.

## Additional findings

1. The logo file is present in the repository:
   - `public/assets/logo-nfa-da.jpg`
2. The Apache rewrite rule is still hardcoded to the local-style subfolder path:
   - `public/.htaccess` contains `RewriteBase /DTS/public/`
3. The deployment URL shown in the screenshot uses `.../DTS/Public/` with an uppercase `P`, while the project directory in the codebase is `public` in lowercase.

## Likely root causes

### 1. URL root is not explicitly configured for production

The application depends on `$_SERVER['HTTP_HOST']`. In many server migrations, reverse proxy setups, virtual host rules, or Apache/Nginx forwarding can cause PHP to receive an unexpected host value such as `localhost`.

### 2. Absolute asset links rely on the same faulty base URL

Because the logo path is generated from `URLROOT`, a wrong base host breaks the image even if the file exists on disk.

### 3. Rewrite base may not match the actual server deployment path

If the production server is not serving the app from exactly `/DTS/public/`, the hardcoded rewrite base can cause incorrect routing behavior.

### 4. Path case mismatch may affect Linux hosting

If the migrated server runs Linux, `Public` and `public` are different paths. A mismatch between the accessed URL and the actual folder name can break routes and static file loading.

## Recommendations

### Immediate corrective actions

1. Set a fixed production `URLROOT` instead of deriving it dynamically from `$_SERVER['HTTP_HOST']`.
   - Example target:
   - `https://dts.nfa.gov.ph/DTS/public`
2. Verify the web server or virtual host is serving the application from the correct public directory.
3. Update `.htaccess` `RewriteBase` so it matches the real deployed path.
4. Standardize the public URL path casing and use `public` consistently.
5. Test these URLs directly after the change:
   - `/DTS/public/assets/logo-nfa-da.jpg`
   - `/DTS/public/auth/login`
   - `/DTS/public/auth/register`
   - login redirect to `/DTS/public/dashboard`

### Windows Apache deployment note

For a Windows Apache/XAMPP host, set the canonical URL in Apache so PHP does not infer `localhost`:

```apache
SetEnv APP_URL https://dts.nfa.gov.ph/DTS/public
SetEnv APP_SUBDIR /DTS/public
```

Recommended locations:

1. The site VirtualHost config, if available.
2. The app `.htaccess`, if `AllowOverride` permits environment settings.

Example VirtualHost snippet:

```apache
<VirtualHost *:80>
    ServerName dts.nfa.gov.ph
    DocumentRoot "C:/xampp/htdocs/DTS/public"

    SetEnv APP_URL https://dts.nfa.gov.ph/DTS/public
    SetEnv APP_SUBDIR /DTS/public

    <Directory "C:/xampp/htdocs/DTS/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Recommended hardening

1. Move environment-specific settings into a dedicated production config or environment file.
2. Avoid hardcoding local database defaults like `DB_HOST=localhost` and blank passwords in committed config for production use.
3. Add a deployment checklist covering:
   - base URL
   - rewrite base
   - public directory mapping
   - path casing
   - asset accessibility
   - login redirect verification

## Conclusion

This is most likely a deployment configuration problem, not a missing-logo code problem. The logo is present, but the app is generating links against the wrong host. Once `URLROOT`, rewrite base, and path casing are aligned with the production server, both the broken logo and the redirect to `localhost` should be resolved together.
