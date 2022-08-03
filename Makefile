.PHONY: all php-cs-fixer psalm phpunit

all:	php-cs-fixer psalm phpunit

phpunit:
	vendor/bin/phpunit

php-cs-fixer:
	vendor/bin/php-cs-fixer fix

psalm:
	vendor/bin/psalm
