HTTP_PORT ?= 8082

.PHONY: all test fmt psalm phpstan sloc dev

all:	test fmt psalm phpstan

test:
	vendor/bin/put

fmt:
	php-cs-fixer fix

psalm:
	psalm

phpstan:
	phpstan

sloc:
	phploc src web libexec bin

dev:
	@php -S localhost:$(HTTP_PORT) -t web dev/router.php
