---
name: logging-packages
description: Lists WyriHaximus PSR-3 and Monolog packages for logging work. Use when working with PSR-3, Monolog, log decorators, log filters, or structured logging.
---

# Logging packages

## Packages to consider when working with logging

- [`wyrihaximus/psr-3-context-logger`](https://github.com/WyriHaximus/php-psr-3-context-logger) — PSR-3 decorator; merge default context (optional `[Prefix]`) into every log call
- [`wyrihaximus/psr-3-filter`](https://github.com/WyriHaximus/php-psr-3-filter) — PSR-3 filter decorators; pass or drop logs by context path, level, message keyword, or strip nested `[Prefix]` chains (pairs with context-logger)
- [`wyrihaximus/psr-3-callable-throwable-logger`](https://github.com/WyriHaximus/php-psr-3-callable-throwable-logger) — `CallableThrowableLogger::create()` for react/promise rejection handlers and RxPHP error callbacks
- [`wyrihaximus/monolog-processors`](https://github.com/WyriHaximus/php-monolog-processors) — Monolog record processors (`CopyProcessor`, `ExceptionClassProcessor`, `TraceProcessor`, `RuntimeProcessor`, …)
