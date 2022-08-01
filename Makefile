.PHONY: all update test fix psalm

all:	update test fix psalm

update:
	composer update

test:
	vendor/bin/phpunit

fix:
	vendor/bin/php-cs-fixer fix

psalm:
	vendor/bin/psalm
