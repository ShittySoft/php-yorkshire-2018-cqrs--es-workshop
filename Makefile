.PHONY: *

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

all: static-analysis ## run all checks

static-analysis: ## verify code style rules
	vendor/bin/phpstan analyse

composer-require-checker: ## check whether there are used dependencies that aren't in `composer.json`
	vendor/bin/composer-require-checker
