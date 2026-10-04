# Makefiles package (etc/)

## Config migrations

When an existing consumer file may lack a new key, prefer **one migration target per key** in `includes/*.Migrations.mk`: a `php -r` one-liner that exits if the file is missing, exits if the key is already present (`preg_match('/^\s*KEY\s*=/m', $content)`), otherwise **prepends** `KEY = value\n` to the file (`file_put_contents($configFile, $line . $content)`).

Do not add PHP classes for this. Update `etc/base64/` and run `make install` to verify migrations and Makefile task lists. New checkouts get the full file from `migrations-*-create-*-if-not-exists`.

Migration `####` help text must not contain `=` (it breaks task-list injection into `on-install-or-update`).
