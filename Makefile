setup:
	@make build
	@make up 
	@make permissions
	@make composer-install
	@make composer-update
	@make data
build:
	docker compose build --no-cache --force-rm
stop:
	docker compose stop
up:
	docker compose up -d
permissions:
	docker compose exec -u root app chown -R Guilherme:Guilherme /var/www
composer-install:
	docker compose exec -u Guilherme app bash -c "composer install"
data:
	docker exec -u Guilherme app bash -c "php artisan migrate"
	docker exec -u Guilherme app bash -c "php artisan db:seed"
destroy:
	docker stop $(docker ps -aq) 2>/dev/null
	docker rm $(docker ps -aq) 2>/dev/null
	docker rmi $(docker images -q) -f 2>/dev/null
	docker volume rm $(docker volume ls -q) 2>/dev/null
	docker network prune -f
	docker builder prune -a -f
	docker system prune -a --volumes -f