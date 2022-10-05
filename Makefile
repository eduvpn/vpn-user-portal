.PHONY: all test fmt psalm phpstan sloc

all:	test fmt psalm phpstan

test:
	vendor/bin/phpunit

fmt:
	vendor/bin/php-cs-fixer fix

psalm:
	vendor/bin/psalm

phpstan:
	vendor/bin/phpstan

sloc:
	vendor/bin/phploc src web libexec bin
