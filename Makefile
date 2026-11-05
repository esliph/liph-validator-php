ARGS ?=

down:
	@docker compose down -v $(ARGS)
.PHONY: down

deps:
	@docker compose --profile deps run --rm composer install $(ARGS)
.PHONY: deps

cli:
	@docker compose --profile cli run --rm validator bash $(ARGS)
.PHONY: cli

test:
	@docker compose --profile test run --rm validator composer run test -- $(ARGS)
.PHONY: test

analyse:
	@docker compose --profile test run --rm validator composer run analyse -- $(ARGS)
.PHONY: analyse

cov:
	@docker compose --profile coverage run --rm validator composer run test:coverage -- $(ARGS)
.PHONY: coverage

cov_html:
	@docker compose --profile coverage run --rm validator composer run test:coverage:html -- $(ARGS)
.PHONY: coverage
