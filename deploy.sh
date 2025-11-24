#!/bin/bash

# ============================================
# Marketing Dashboard Deployment Script
# ============================================
# Usage: ./deploy.sh
# Description: Deploys latest updates from GitHub to production
# ============================================

set -e  # Exit on any error

# Colors for output (optimized for dark terminal background)
RED='\033[1;91m'
GREEN='\033[1;92m'
YELLOW='\033[1;93m'
BLUE='\033[1;96m'
NC='\033[0m'

# Configuration
APP_DIR="/var/www/html/web/marketing-dashboard"
BRANCH="main"
BACKUP_DIR="/var/www/backups/marketing-dashboard"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Functions
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

# Backup current state
backup_current_state() {
    print_header "Creating Backup"
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup .env file
    if [ -f "$APP_DIR/.env" ]; then
        cp "$APP_DIR/.env" "$BACKUP_DIR/.env.$TIMESTAMP"
        print_success ".env backed up"
    fi
    
    # Get current git commit
    cd "$APP_DIR"
    CURRENT_COMMIT=$(git rev-parse HEAD)
    echo "$CURRENT_COMMIT" > "$BACKUP_DIR/commit.$TIMESTAMP"
    print_success "Current commit saved: $CURRENT_COMMIT"
}

# Main deployment process
main() {
    print_header "Starting Deployment - $(date)"
    
    # Navigate to app directory
    if [ ! -d "$APP_DIR" ]; then
        print_error "Application directory not found: $APP_DIR"
        exit 1
    fi
    
    cd "$APP_DIR"
    print_success "Changed to application directory"
    
    # Backup current state
    backup_current_state
    
    # Enable maintenance mode
    print_header "Enabling Maintenance Mode"
    php artisan down --retry=60 || print_info "Maintenance mode already enabled or failed"
    
    # Pull latest code
    print_header "Pulling Latest Code"
    print_info "Fetching from origin/$BRANCH..."
    
    git fetch origin "$BRANCH"
    
    # Check if there are updates
    LOCAL=$(git rev-parse HEAD)
    REMOTE=$(git rev-parse origin/"$BRANCH")
    
    if [ "$LOCAL" = "$REMOTE" ]; then
        print_info "Already up to date. No changes to deploy."
        php artisan up
        exit 0
    fi
    
    print_info "Changes detected. Pulling updates..."
    git pull origin "$BRANCH"
    print_success "Code updated successfully"
    
    # Install/Update Composer dependencies
    print_header "Updating Composer Dependencies"
    composer install --no-dev --optimize-autoloader --no-interaction
    print_success "Composer dependencies updated"
    
    # Install/Update NPM dependencies
    print_header "Updating NPM Dependencies"
    npm ci --production=false
    print_success "NPM dependencies updated"
    
    # Build frontend assets
    print_header "Building Frontend Assets"
    npm run build
    print_success "Assets built successfully"
    
    # Run database migrations
    print_header "Running Database Migrations"
    php artisan migrate --force
    print_success "Migrations completed"
    
    # Clear and cache configuration
    print_header "Optimizing Application"
    
    echo "Clearing caches..."
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    print_success "Caches cleared"
    
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    print_success "Configuration cached"
    
    # Optimize autoloader
    echo "Optimizing autoloader..."
    composer dump-autoload --optimize
    print_success "Autoloader optimized"
    
    # Set proper permissions
    print_header "Setting Permissions"
    chmod -R 775 storage bootstrap/cache
    print_success "Permissions set"
    
    # Restart queue workers
    print_header "Restarting Queue Workers"
    php artisan queue:restart
    print_success "Queue workers restarted"
    
    # Verify scheduler
    print_header "Verifying Scheduler"
    php artisan schedule:list
    
    # Check latest sync status
    print_header "Checking Sync Status"
    php artisan app:sync-status --latest || print_info "Sync status check skipped"
    
    # Disable maintenance mode
    print_header "Disabling Maintenance Mode"
    php artisan up
    print_success "Application is now live"
    
    # Final summary
    print_header "Deployment Summary"
    echo -e "Deployed at: ${GREEN}$(date)${NC}"
    echo -e "Previous commit: ${YELLOW}$LOCAL${NC}"
    echo -e "Current commit: ${GREEN}$REMOTE${NC}"
    echo -e "Backup location: ${BLUE}$BACKUP_DIR${NC}"
    
    print_success "Deployment completed successfully!"
}

# Run main function
main

exit 0
