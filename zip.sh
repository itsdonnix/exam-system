#!/usr/bin/env bash

# Configuration
BUILD_DIR="dist_temp"
ZIP_NAME="exam-system.zip"

echo "🚀 Starting build process..."
rm -rf "$BUILD_DIR" && mkdir "$BUILD_DIR"

# 1. Copy project files (excluding dev junk)
rsync -av . "$BUILD_DIR" \
    --exclude '.git*' --exclude '*.diff' --exclude '*.bak' --exclude '*.zip' \
    --exclude '*.sh' --exclude 'node_modules' --exclude 'package*.json' \
    --exclude 'composer.*' --exclude '*.sql' --exclude '.DS_Store' \
    --exclude 'uploads' --exclude '*.log' --exclude '*.sh'

echo "⚡ Minifying JavaScript (Terser)..."
find "$BUILD_DIR" -name "*.js" -type f ! -name "*.min.js" -exec npx terser {} -o {} --compress --mangle \;

echo "🎨 Minifying CSS (Clean-CSS)..."
find "$BUILD_DIR" -name "*.css" -type f ! -name "*.min.css" -exec npx cleancss -o {} {} \;

echo "🌐 Minifying HTML (html-minifier-terser)..."
# We use standard safe flags: collapse whitespace and remove comments
find "$BUILD_DIR" -name "*.html" -type f | while read file; do
    npx html-minifier-terser "$file" -o "$file" --collapse-whitespace --remove-comments --minify-js true --minify-css true
done

# echo "🐘 Cleaning PHP (Stripping comments)..."
# find "$BUILD_DIR" -name "*.php" -type f | while read file; do
#     php -r "file_put_contents('$file', php_strip_whitespace('$file'));"
# done

# 2. Archive and Cleanup
echo "📦 Archiving..."
cd "$BUILD_DIR" && zip -r "../$ZIP_NAME" . && cd ..
rm -rf "$BUILD_DIR"

echo "✅ Done! Created $ZIP_NAME"
