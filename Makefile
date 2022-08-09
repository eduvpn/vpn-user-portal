.PHONY: all test fmt psalm phpstan

all:	test fmt psalm phpstan

test:
	vendor/bin/phpunit

fmt:
	vendor/bin/php-cs-fixer fix

psalm:
	vendor/bin/psalm

phpstan:
	vendor/bin/phpstan
