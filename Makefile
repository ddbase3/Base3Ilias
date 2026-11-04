# ==============================================================================
# BASE3 / ILIAS PHPUnit tooling (PHPUnit 11.5)
#
#   make test         Run the tests of all BASE3 modules
#   make test-issues  Run the same tests and display all issues
#   make coverage     Generate HTML coverage in ILIAS/public/docs/coverage
#
# BASE3 modules are discovered by their base3.json manifests anywhere below
# ILIAS/components/. Each module contributes its test/ and src/ directories.
# ==============================================================================

SHELL := /bin/bash

BASE3_ILIAS_DIR := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
TEST_SCRIPT     := $(BASE3_ILIAS_DIR)/tools/test-base3.sh

.PHONY: test test-issues coverage

test:
	@bash "$(TEST_SCRIPT)" --no-coverage

test-issues:
	@bash "$(TEST_SCRIPT)" --no-coverage --display-all-issues

coverage:
	@bash "$(TEST_SCRIPT)" --coverage-html public/docs/coverage --coverage-text

