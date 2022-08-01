.PHONY: all test fix psalm

all:	test fix psalm

test:
	vendor/bin/phpunit

fix:
	vendor/bin/php-cs-fixer fix

psalm:
	vendor/bin/psalm
