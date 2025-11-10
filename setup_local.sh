#!/bin/bash

# ============================================================================
# Local Setup Script - Hotel Dashboard
# ============================================================================

echo "=========================================================================="
echo "🔧 Setting up Hotel Occupancy Prediction Dashboard for Local Testing"
echo "=========================================================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo -e "${RED}❌ Error: composer.json not found${NC}"
    echo "Please run this script from the project root directory"
    exit 1
fi

# Step 1: Laravel Setup
echo -e "${YELLOW}📦 Step 1: Installing Laravel dependencies...${NC}"
composer install --no-interaction --prefer-dist --optimize-autoloader

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Laravel dependencies installed${NC}"
else
    echo -e "${RED}❌ Failed to install Laravel dependencies${NC}"
    exit 1
fi
echo ""

# Step 2: Environment File
echo -e "${YELLOW}📝 Step 2: Setting up .env file...${NC}"
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${GREEN}✅ .env file created from .env.example${NC}"
    else
        # Create minimal .env
        cat > .env << 'ENVFILE'
APP_NAME="Hotel Dharma Utama Dashboard"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=sqlite

SESSION_DRIVER=file
SESSION_LIFETIME=120
ENVFILE
        echo -e "${GREEN}✅ Minimal .env file created${NC}"
    fi
else
    echo -e "${GREEN}✅ .env file already exists${NC}"
fi
echo ""

# Step 3: Generate App Key
echo -e "${YELLOW}🔑 Step 3: Generating application key...${NC}"
php artisan key:generate --ansi
echo ""

# Step 4: Clear Caches
echo -e "${YELLOW}🗑️  Step 4: Clearing Laravel caches...${NC}"
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo -e "${GREEN}✅ Caches cleared${NC}"
echo ""

# Step 5: Python Setup
echo -e "${YELLOW}🐍 Step 5: Setting up Python environment...${NC}"

cd flask_api

# Create virtual environment if not exists
if [ ! -d "venv" ]; then
    echo "Creating Python virtual environment..."
    python3 -m venv venv
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Virtual environment created${NC}"
    else
        echo -e "${RED}❌ Failed to create virtual environment${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✅ Virtual environment already exists${NC}"
fi

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate 2>/dev/null || . venv/Scripts/activate 2>/dev/null

# Install Python dependencies
echo "Installing Python dependencies..."
pip install --upgrade pip -q
pip install -r requirements.txt -q

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Python dependencies installed${NC}"
else
    echo -e "${RED}❌ Failed to install Python dependencies${NC}"
    exit 1
fi

cd ..
echo ""

# Step 6: Create log directory
echo -e "${YELLOW}📁 Step 6: Creating log directory...${NC}"
mkdir -p logs
echo -e "${GREEN}✅ Log directory created${NC}"
echo ""

# Check if model files exist
echo -e "${YELLOW}🔍 Step 7: Checking model files...${NC}"
if [ -f "models/checkpoints/best_model_optimized.h5" ]; then
    echo -e "${GREEN}✅ LSTM model found${NC}"
else
    echo -e "${RED}⚠️  LSTM model not found: models/checkpoints/best_model_optimized.h5${NC}"
fi

if [ -f "models/scaler_X_optimized.pkl" ]; then
    echo -e "${GREEN}✅ Feature scaler found${NC}"
else
    echo -e "${RED}⚠️  Feature scaler not found: models/scaler_X_optimized.pkl${NC}"
fi

if [ -f "models/scaler_y_optimized.pkl" ]; then
    echo -e "${GREEN}✅ Target scaler found${NC}"
else
    echo -e "${RED}⚠️  Target scaler not found: models/scaler_y_optimized.pkl${NC}"
fi

if [ -f "monthly_enhanced_features.csv" ]; then
    echo -e "${GREEN}✅ Historical data found${NC}"
else
    echo -e "${RED}⚠️  Historical data not found: monthly_enhanced_features.csv${NC}"
fi

echo ""
echo "=========================================================================="
echo -e "${GREEN}✅ Setup Complete!${NC}"
echo "=========================================================================="
echo ""
echo "📋 Next steps:"
echo "   1. Start the dashboard:"
echo "      ${GREEN}./start_dashboard.sh${NC}"
echo ""
echo "   2. Or start services manually:"
echo "      Terminal 1: ${GREEN}cd flask_api && source venv/bin/activate && python app.py${NC}"
echo "      Terminal 2: ${GREEN}php artisan serve${NC}"
echo ""
echo "   3. Access dashboard:"
echo "      ${GREEN}http://localhost:8000/dashboard${NC}"
echo ""
echo "=========================================================================="
