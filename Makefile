.PHONY: all test fmt psalm phpstan sloc

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
