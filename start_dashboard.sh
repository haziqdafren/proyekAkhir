#!/bin/bash

# ============================================================================
# Start Dashboard Script - Hotel Dharma Utama Occupancy Prediction
# ============================================================================

echo "=========================================================================="
echo "🏨 Hotel Dharma Utama - Occupancy Prediction Dashboard"
echo "=========================================================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if Python is installed
if ! command -v python3 &> /dev/null; then
    echo -e "${RED}✗ Python3 is not installed${NC}"
    echo "Please install Python 3.8+ first"
    exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗ PHP is not installed${NC}"
    echo "Please install PHP 8.1+ first"
    exit 1
fi

echo -e "${GREEN}✓ Python found: $(python3 --version)${NC}"
echo -e "${GREEN}✓ PHP found: $(php --version | head -n 1)${NC}"
echo ""

# Check if Flask dependencies are installed
echo -e "${YELLOW}Checking Python dependencies...${NC}"
cd flask_api
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
fi

source venv/bin/activate 2>/dev/null || . venv/Scripts/activate 2>/dev/null

echo "Installing/Updating Python dependencies..."
pip install -q -r requirements.txt

cd ..

echo -e "${GREEN}✓ Python dependencies ready${NC}"
echo ""

# Create log directory
mkdir -p logs

# Start Flask API in background
echo -e "${YELLOW}Starting Flask API (ML Service)...${NC}"
cd flask_api
source venv/bin/activate 2>/dev/null || . venv/Scripts/activate 2>/dev/null
python app.py > ../logs/flask_api.log 2>&1 &
FLASK_PID=$!
cd ..

# Wait for Flask to start
sleep 3

# Check if Flask is running
if ps -p $FLASK_PID > /dev/null; then
   echo -e "${GREEN}✓ Flask API started successfully (PID: $FLASK_PID)${NC}"
   echo -e "  URL: ${GREEN}http://localhost:5000${NC}"
else
   echo -e "${RED}✗ Flask API failed to start${NC}"
   echo "Check logs/flask_api.log for details"
   exit 1
fi

echo ""

# Start Laravel server
echo -e "${YELLOW}Starting Laravel Server...${NC}"
php artisan serve > logs/laravel.log 2>&1 &
LARAVEL_PID=$!

# Wait for Laravel to start
sleep 2

# Check if Laravel is running
if ps -p $LARAVEL_PID > /dev/null; then
   echo -e "${GREEN}✓ Laravel server started successfully (PID: $LARAVEL_PID)${NC}"
   echo -e "  URL: ${GREEN}http://localhost:8000${NC}"
else
   echo -e "${RED}✗ Laravel failed to start${NC}"
   echo "Check logs/laravel.log for details"
   kill $FLASK_PID 2>/dev/null
   exit 1
fi

echo ""
echo "=========================================================================="
echo -e "${GREEN}✓ Dashboard is now running!${NC}"
echo "=========================================================================="
echo ""
echo -e "📊 ${GREEN}Dashboard URL:${NC} http://localhost:8000/dashboard"
echo -e "🤖 ${GREEN}ML API URL:${NC}    http://localhost:5000/api"
echo ""
echo "📝 Logs:"
echo "   - Flask API: logs/flask_api.log"
echo "   - Laravel:   logs/laravel.log"
echo ""
echo "🛑 To stop services:"
echo "   kill $FLASK_PID $LARAVEL_PID"
echo "   or press Ctrl+C"
echo ""
echo "=========================================================================="

# Save PIDs for cleanup
echo $FLASK_PID > logs/flask.pid
echo $LARAVEL_PID > logs/laravel.pid

# Wait for user interrupt
trap "echo ''; echo 'Stopping services...'; kill $FLASK_PID $LARAVEL_PID 2>/dev/null; rm -f logs/*.pid; echo 'Services stopped.'; exit 0" INT TERM

# Keep script running
wait
