#!/bin/bash

# ============================================
# Marketing Dashboard Rollback Script
# ============================================
# Usage: ./rollback.sh [commit_hash or timestamp]
# Description: Rollback to previous version
# ============================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
APP_DIR="/var/www/html/web/marketing-dashboard"
BACKUP_DIR="/var/www/backups/marketing-dashboard"

print_header() {
    echo -e "${BLUE}============================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}============================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

list_backups() {
    print_header "Available Backups"
    
    if [ ! -d "$BACKUP_DIR" ]; then
        print_error "No backups found"
        exit 1
    fi
    
    echo "Recent commits backed up:"
    ls -lt "$BACKUP_DIR"/commit.* | head -10 | while read -r line; do
        file=$(echo "$line" | awk '{print $NF}')
        timestamp=$(basename "$file" | sed 's/commit.//')
        commit=$(cat "$file")
        echo -e "${YELLOW}$timestamp${NC} - ${GREEN}$commit${NC}"
    done
}

rollback() {
    TARGET=$1
    
    print_header "Starting Rollback"
    
    cd "$APP_DIR"
    
    # Enable maintenance mode
    print_info "Enabling maintenance mode..."
    php artisan down --retry=60
    
    # Rollback git
    if [ -z "$TARGET" ]; then
        # List recent commits
        print_info "Recent commits:"
        git log --oneline -10
        echo ""
        print_error "Please specify commit hash or timestamp"
        print_info "Usage: ./rollback.sh <commit_hash>"
        print_info "Or: ./rollback.sh <timestamp> (from backup list)"
        php artisan up
        exit 1
    fi
    
    # Check if target is timestamp or commit
    if [ -f "$BACKUP_DIR/commit.$TARGET" ]; then
        COMMIT=$(cat "$BACKUP_DIR/commit.$TARGET")
        print_info "Rolling back to commit from backup: $COMMIT"
    else
        COMMIT=$TARGET
        print_info "Rolling back to commit: $COMMIT"
    fi
    
    # Perform rollback
    print_info "Checking out commit..."
    git checkout "$COMMIT"
    print_success "Code rolled back"
    
    # Restore .env if exists
    if [ -f "$BACKUP_DIR/.env.$TARGET" ]; then
        print_info "Restoring .env file..."
        cp "$BACKUP_DIR/.env.$TARGET" "$APP_DIR/.env"
        print_success ".env restored"
    fi
    
    # Reinstall dependencies
    print_info "Reinstalling dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    npm ci --production=false
    npm run build
    print_success "Dependencies reinstalled"
    
    # Clear caches
    print_info "Clearing caches..."
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    print_success "Caches cleared and rebuilt"
    
    # Restart queue
    php artisan queue:restart
    print_success "Queue workers restarted"
    
    # Disable maintenance mode
    php artisan up
    print_success "Maintenance mode disabled"
    
    print_header "Rollback Complete"
    print_success "Application rolled back to: $COMMIT"
}

# Main
if [ "$1" = "--list" ] || [ "$1" = "-l" ]; then
    list_backups
else
    rollback "$1"
fi

exit 0
