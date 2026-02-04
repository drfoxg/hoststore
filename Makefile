up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

artisan:
	docker compose exec app php artisan $(cmd)

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed

logs:
	docker compose logs -f

test:
	docker compose exec app php artisan test
