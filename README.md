# pikoman/compat-laravel

A Laravel broadcast driver for [Pikoman](https://pikoman.se). Your events, `routes/channels.php`
and Laravel Echo code stay as they are with Pusher; the browser uses `@pikoman/compat` in place of
pusher-js. Laravel 11, 12 and 13, PHP 8.2 or later with the curl, openssl and sodium extensions.
It is built on [`pikoman/compat-php`](https://packagist.org/packages/pikoman/compat-php), which
Composer installs with it, and which is also for PHP without Laravel.

## Install

```bash
composer require pikoman/compat-laravel
npm install @pikoman/compat
```

`.env`:

```
BROADCAST_CONNECTION=pikoman
PIKOMAN_APP=app-yourapp
PIKOMAN_API_KEY=sk_...
PIKOMAN_PRIVATE_KEY=MC4CAQAwBQYDK2Vw...
VITE_PUSHER_APP_KEY="${PIKOMAN_APP}"
```

`resources/js/echo.js`: `import Pusher from "@pikoman/compat";` in place of `pusher-js`.

Then `php artisan config:clear` and `npm run build`.

## What it covers

Public, private, presence and encrypted channels (`EncryptedPrivateChannel`, with
`PIKOMAN_ENCRYPTION_KEY` in `.env`: 32 random bytes, base64, which Pikoman never sees),
`routes/channels.php` authorization (presence with the info your callback returns), and
broadcasting events to up to 100 channels at a time, and `toOthers()` (with Echo's `X-Socket-ID`,
as with Pusher). Missing something you use? Write to hello@pikoman.se.

Source: [github.com/pikomanse/compat-laravel](https://github.com/pikomanse/compat-laravel). Switching
from Pusher, step by step: [pikoman.se/switch-from-pusher](https://pikoman.se/switch-from-pusher/).

MIT licensed.
