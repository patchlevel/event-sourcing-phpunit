help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

vendor: composer.lock
	composer install

vendor-tools: tools/composer.lock
	cd tools && composer install

.PHONY: cs-check
cs-check: vendor                                                                ## run phpcs
	vendor/bin/phpcs

.PHONY: cs
cs: vendor                                                                      ## run phpcs fixer
	vendor/bin/phpcbf || true
	vendor/bin/phpcs

.PHONY: phpstan
phpstan: vendor                                                                 ## run phpstan static code analyser
	php -d memory_limit=312M vendor/bin/phpstan analyse

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	php -d memory_limit=312M vendor/bin/phpstan analyse --generate-baseline

.PHONY: phpunit
phpunit: vendor phpunit-unit                              						## run phpunit tests

.PHONY: phpunit-unit
phpunit-unit: vendor                                             				## run phpunit unit tests
	XDEBUG_MODE=coverage vendor/bin/phpunit --testsuite=unit

.PHONY: infection
infection: vendor                                                               ## run infection
	php -d memory_limit=312M vendor/bin/infection --threads=max

.PHONY: static
static: phpstan cs                                              			 	## run static analyser

test: phpunit                                                                   ## run tests

.PHONY: dev
dev: static test                                                                ## run dev tools
