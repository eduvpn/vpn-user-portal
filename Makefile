.PHONY: all test php-cs-fixer psalm phpstan phpunit

all:	php-cs-fixer psalm phpunit

test:	phpunit

phpunit:
	vendor/bin/phpunit

php-cs-fixer:
	vendor/bin/php-cs-fixer fix

psalm:
	vendor/bin/psalm

phpstan:
	vendor/bin/phpstan
