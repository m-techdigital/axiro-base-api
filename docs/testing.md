# Testing
Run the API gates that match the changed surface:

- `./vendor/bin/pint --test`
- `php artisan test`
- `php artisan migrate:fresh --seed` when migrations/seeders change
- `php artisan marketplace:integrity` when marketplace lifecycle or demo data changes

For changed files, also run targeted `php -l`/focused tests around document, payout, transaction and contract resources.
