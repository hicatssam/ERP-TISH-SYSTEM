# TISH customer Web Push: release checklist

This feature is intentionally isolated in a draft PR until the full Laravel/browser/server tests pass.

## Dependencies and secrets
- PHP OpenSSL extension and the already-installed Laravel HTTP client; no third-party Web Push Composer dependency.
- Configure `TISH_VAPID_PUBLIC_KEY`, `TISH_VAPID_PRIVATE_KEY`, and `TISH_VAPID_SUBJECT=mailto:admin@your-real-domain.example` on **the server only**.
- Generate one P-256 keypair privately on the server. Example with PHP:
  ```sh
  php -r '$k=openssl_pkey_new(["private_key_type"=>OPENSSL_KEYTYPE_EC,"curve_name"=>"prime256v1"]);$d=openssl_pkey_get_details($k)["ec"]; $e=fn($v)=>rtrim(strtr(base64_encode($v),"+/","-_"),"=");echo "TISH_VAPID_PUBLIC_KEY=".$e("\x04".$d["x"].$d["y"]).PHP_EOL."TISH_VAPID_PRIVATE_KEY=".$e($d["d"]).PHP_EOL;'
  ```
- Treat the private key like a password. Never commit it or log it. **Do not rotate it casually**: existing browser subscriptions are tied to the old public key.
- Do not confuse `TISH_VAPID_SUBJECT` with the customer email; it must be a real `mailto:` or `https:` contact URI.

## Deployment order
1. Confirm current production Git commit; back up database and `.env`.
2. Ensure normal Laravel `schedule:run` runs once per minute and works with cache locks. For near-real-time KDS events, run the Laravel default queue worker (`php artisan queue:work --queue=default`) under a process supervisor. When queue is sync or the worker is down, the schedule is the recovery fallback. Keep `APP_KEY` stable (subscription keys are encrypted with it).
3. Pull only an already-tested merged commit, not the unreviewed draft branch.
4. Run `php artisan migrate --force`, `php artisan optimize:clear`, `php artisan config:cache`.
5. Check `GET /menu/push/public-key` responds `enabled:true` **only after VAPID configuration**.
6. Run `php artisan tish:dispatch-push` manually and check logs; recurring cron calls the same command.
7. On HTTPS, place an order, opt in from the order tracking page, advance the kitchen ticket to preparing then ready, and verify a background notification on a real browser/device.
8. Confirm 404/410 endpoints are pruned, multiple orders on one phone both notify, and two tabs do not play repetitive foreground sounds.

## Platform caveats
- Browser permission and `PushManager.subscribe` must be triggered by a user gesture, and HTTPS is required.
- iOS requires a supporting iOS/browser environment; home-screen web app installation is necessary on iOS 16.4+ for classical Web Push.
- The custom Web Audio burger sizzle can only play when the page is allowed to run audio. **A custom sound cannot be guaranteed for a background Web Push**; the OS controls the audible notification.
- KDS/order model events enqueue notifications after commit when an asynchronous queue is configured. The Laravel minute scheduler is an eventual-delivery fallback; it may only see the latest state if several KDS transitions happen before its next run. Background delivery also depends on push-provider and OS behavior.
- An estimated deadline is approximate, not a kitchen promise. Older orders without one show minutes only.
- Check actual PHP/browser test results before production rollout; do not infer success from code review alone.
