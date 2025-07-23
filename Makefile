# Sitechips Plugin Framework - Makefile
# =====================================

# Configuration Variables
PLUGIN_NAME = sitechips-boilerplate
PLUGIN_DIR = web/wp-content/plugins/$(PLUGIN_NAME)
TEST_DIR = tests
LIB_DIR = lib

# Remote Server Configuration (adjust to your needs)
SSH_SERVER = p663339.webspaceconfig.de
SSH_USER = p663339
REMOTE_PLUGIN_DIR = html/wordpress/wp-content/plugins/$(PLUGIN_NAME)/

# Release Server Configuration
RELEASE_SERVER = release.qbus.de
RELEASE_USER = qbus
RELEASE_DIR = /home/qbus/www/release-server/packages/

# Colors for output
YELLOW = \033[1;33m
GREEN = \033[1;32m
NC = \033[0m # No Color

# Default target
.DEFAULT_GOAL := help

# ===================================
# PLUGIN DEVELOPER COMMANDS
# ===================================

.PHONY: help
help: ## Show this help message
	@echo "$(GREEN)Sitechips Plugin Framework$(NC)"
	@echo ""
	@echo "$(YELLOW)Plugin Developer Commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## Plugin:' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## Plugin:"}; {printf "  %-20s %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Framework Developer Commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## Framework:' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## Framework:"}; {printf "  %-20s %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Deployment Commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## Deploy:' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## Deploy:"}; {printf "  %-20s %s\n", $$1, $$2}'

.PHONY: test-plugin
test-plugin: ## Plugin: Run plugin tests only
	cd $(PLUGIN_DIR) && vendor/bin/phpunit --testsuite=Plugin

.PHONY: quality-plugin
quality-plugin: ## Plugin: Run quality checks (PHPCS + PHPStan)
	cd $(PLUGIN_DIR) && vendor/bin/phpcs src/
	cd $(PLUGIN_DIR) && vendor/bin/phpstan analyse src/

.PHONY: watch-plugin
watch-plugin: ## Plugin: Watch plugin files for changes
	@echo "Watching plugin files for changes..."
	@find $(PLUGIN_DIR)/src -name "*.php" | entr -c make test-plugin

# ===================================
# FRAMEWORK DEVELOPER COMMANDS
# ===================================

.PHONY: test-all
test-all: ## Framework: Run all tests (Core + Plugin)
	cd $(PLUGIN_DIR) && vendor/bin/phpunit --testsuite=All

.PHONY: test-core
test-core: ## Framework: Run core framework tests only
	cd $(PLUGIN_DIR) && vendor/bin/phpunit --testsuite=Core

.PHONY: test-coverage
test-coverage: ## Framework: Generate test coverage report
	cd $(PLUGIN_DIR) && vendor/bin/phpunit --testsuite=Framework --coverage-html coverage/

.PHONY: quality-all
quality-all: ## Framework: Full quality check (PHPCS + PHPStan + Tests)
	cd $(PLUGIN_DIR) && vendor/bin/phpcs
	cd $(PLUGIN_DIR) && vendor/bin/phpstan analyse
	cd $(PLUGIN_DIR) && vendor/bin/phpunit --testsuite=Framework

.PHONY: sync-core
sync-core: ## Framework: Sync core files between lib/ directories
	@echo "Syncing core framework files..."
	rsync -av --delete \
		$(LIB_DIR)/ \
		$(PLUGIN_DIR)/lib/

# ===================================
# DEPLOYMENT COMMANDS
# ===================================

.PHONY: download-from-dev
download-from-dev: ## Deploy: Download plugin from development server
	rsync -avz \
		--exclude .git \
		-e ssh $(SSH_USER)@$(SSH_SERVER):$(REMOTE_PLUGIN_DIR) ./

.PHONY: upload-to-dev
upload-to-dev: ## Deploy: Upload plugin to development server
	rsync -avz \
		--delete \
		--delete-excluded \
		--exclude .git \
		--exclude .github \
		--exclude node_modules \
		--exclude vendor \
		--exclude tests \
		--exclude coverage \
		--exclude .vscode \
		--exclude '*.log' \
		--exclude '*.zip' \
		--exclude Makefile \
		--exclude phpunit.xml \
		--exclude phpcs.xml \
		--exclude phpstan.neon \
		--exclude composer.* \
		--exclude .strauss.json \
		-e ssh $(PLUGIN_DIR)/ $(SSH_USER)@$(SSH_SERVER):$(REMOTE_PLUGIN_DIR)

.PHONY: build-release
build-release: ## Deploy: Build release ZIP (without dev dependencies)
	@echo "Building release package..."
	cd $(PLUGIN_DIR) && composer install --no-dev --optimize-autoloader
	rm -f $(PLUGIN_NAME).zip
	cd web/wp-content/plugins && zip -r9 ../../../$(PLUGIN_NAME).zip $(PLUGIN_NAME)/ \
		-x "*/.*" \
		-x "*/tests/*" \
		-x "*/vendor/*" \
		-x "*/node_modules/*" \
		-x "*/coverage/*" \
		-x "*/Makefile" \
		-x "*/composer.*" \
		-x "*/phpunit.xml" \
		-x "*/phpcs.xml" \
		-x "*/phpstan.neon" \
		-x "*/.strauss.json" \
		-x "*/emergency-restore.php" \
		-x "*/setup.php"
	cd $(PLUGIN_DIR) && composer install
	@echo "Release package created: $(PLUGIN_NAME).zip"

.PHONY: upload-release
upload-release: build-release ## Deploy: Build and upload release to release server
	rsync -avz \
		-e ssh $(PLUGIN_NAME).zip \
		$(RELEASE_USER)@$(RELEASE_SERVER):$(RELEASE_DIR)/

# ===================================
# UTILITY COMMANDS
# ===================================

.PHONY: clean
clean: ## Remove temporary files and caches
	rm -rf $(PLUGIN_DIR)/coverage
	rm -rf $(PLUGIN_DIR)/vendor
	rm -rf $(PLUGIN_DIR)/src/Libs
	rm -f $(PLUGIN_NAME).zip
	find . -name "*.log" -delete

.PHONY: install
install: ## Install all dependencies (with Strauss)
	cd $(PLUGIN_DIR) && composer install
	@echo "Dependencies installed and isolated with Strauss"

.PHONY: pot
pot: ## Generate POT file for translations
	cd $(PLUGIN_DIR) && vendor/bin/wp i18n make-pot \
		--domain=$(PLUGIN_NAME) . languages/$(PLUGIN_NAME).pot

# Quick development helpers
.PHONY: tp
tp: test-plugin ## Alias for test-plugin

.PHONY: tc
tc: test-core ## Alias for test-core

.PHONY: ta
ta: test-all ## Alias for test-all