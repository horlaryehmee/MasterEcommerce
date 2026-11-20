<?php

/*
 * One-click updates (Admin → Updates).
 *
 * Releases are published on GitHub by .github/workflows/release.yml whenever a
 * version tag (e.g. v1.2.0) is pushed. Each release carries a ready-to-run zip
 * (PHP dependencies and compiled assets included), so the server needs no
 * Composer, Node.js or terminal.
 *
 * The repository and token can also be set in the admin (Updates → Update source);
 * values saved there take priority over these.
 */
return [
    // "owner/repository" on GitHub.
    'repository' => env('UPDATE_GITHUB_REPO'),

    // Personal access token. Required for private repositories; recommended for
    // public ones too (shared hosting IPs quickly hit GitHub's anonymous rate limit).
    // Fine-grained token with read-only "Contents" access to the repository is enough.
    'token' => env('UPDATE_GITHUB_TOKEN'),

    // Name of the zip asset attached to each release ({tag} = e.g. v1.2.0).
    'asset' => 'catalog-{tag}.zip',

    // How long a "check for updates" result is cached (seconds).
    'check_ttl' => 6 * 3600,

    // Number of previous versions (files + database backup) kept for restoring.
    'keep_backups' => 3,

    // Top-level entries of the release that are never copied over the live site.
    // .env and storage/ hold the client's settings, uploads and logs; modules/ and
    // extensions/ are updated only by their own releases, never by a core update.
    'preserve' => ['.env', 'storage', 'public', 'modules', 'extensions', '.git', '.github', 'node_modules', '.htaccess', '.user.ini', 'php.ini', 'error_log'],

    // Files in public/ that are never replaced (custom index.php for public_html
    // setups, cPanel's PHP-version .htaccess lines, uploads symlink).
    'public_preserve' => ['index.php', '.htaccess', '.user.ini', 'php.ini', 'storage', 'error_log'],
];
