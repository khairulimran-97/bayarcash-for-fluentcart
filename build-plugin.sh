#!/bin/bash

PLUGIN_DIR="bayarcash-for-fluentcart"
BUILD_DIR="build"
ZIP_NAME="bayarcash-for-fluentcart.zip"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}🚀 Starting Bayarcash for FluentCart Plugin Build...${NC}"

if [ ! -f "bayarcash-for-fluentcart.php" ]; then
    echo -e "${RED}❌ Error: Please run this script from the plugin root directory${NC}"
    exit 1
fi

echo -e "${YELLOW}🧹 Cleaning up existing zip files...${NC}"
rm -f *.zip

echo -e "${YELLOW}📁 Creating build directory...${NC}"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

echo -e "${YELLOW}📋 Copying plugin files...${NC}"
mkdir -p "$BUILD_DIR/$PLUGIN_DIR"

rsync -av --progress \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='._*' \
    --exclude='.git*' \
    --exclude='.svn' \
    --exclude='.hg' \
    --exclude='CVS' \
    --exclude='.idea' \
    --exclude='.vscode' \
    --exclude='*.log' \
    --exclude='*.tmp' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='*.test.php' \
    --exclude='phpunit.xml' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='webpack.config.js' \
    --exclude='gulpfile.js' \
    --exclude='Gruntfile.js' \
    --exclude='*.scss' \
    --exclude='*.sass' \
    --exclude='*.less' \
    --exclude='*.zip' \
    --exclude='build' \
    --exclude='build-plugin.sh' \
    --exclude='INSTALLATION.md' \
    ./ "$BUILD_DIR/$PLUGIN_DIR/"

echo -e "${YELLOW}🧹 Cleaning up hidden files...${NC}"
find "$BUILD_DIR" -name ".DS_Store" -delete
find "$BUILD_DIR" -name "._*" -delete
find "$BUILD_DIR" -name "Thumbs.db" -delete
find "$BUILD_DIR" -name ".gitkeep" -delete

find "$BUILD_DIR" -type d -empty -delete

echo -e "${YELLOW}📦 Creating zip file...${NC}"
cd "$BUILD_DIR"
zip -r -q "../$ZIP_NAME" "$PLUGIN_DIR"
cd ..

echo -e "${YELLOW}🗑️  Cleaning up build directory...${NC}"
rm -rf "$BUILD_DIR"

if [ -f "$ZIP_NAME" ]; then
    FILE_SIZE=$(du -h "$ZIP_NAME" | cut -f1)
    echo -e "${GREEN}✅ Plugin zip created successfully!${NC}"
    echo -e "${GREEN}📁 File: $ZIP_NAME${NC}"
    echo -e "${GREEN}📊 Size: $FILE_SIZE${NC}"
    echo -e "${GREEN}🎉 Ready for WordPress plugin upload!${NC}"
else
    echo -e "${RED}❌ Error: Failed to create zip file${NC}"
    exit 1
fi

echo -e "\n${YELLOW}📋 Zip contents:${NC}"
unzip -l "$ZIP_NAME" | head -20
if [ $(unzip -l "$ZIP_NAME" | wc -l) -gt 25 ]; then
    echo "... (truncated, showing first 20 entries)"
fi

echo -e "\n${GREEN}🚀 Build complete! Upload $ZIP_NAME to WordPress.${NC}"
