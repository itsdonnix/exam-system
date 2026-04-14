#!/usr/bin/env bash
rm -rf exam-system.zip

# 'vendor/*'
zip -r exam-system.zip . -x '*.git*' '*.diff' '*.bak' '*.zip' '*.md' '*.github/*' '*.vscode/*' '*.idea/*' 'node_modules/*' 'storage/logs/*' 'storage/framework/*' 'storage/app/tmp/*' '.env' '.env.*' '*.sh' '*.log' '*.cache' 'tests/*' '*.md' 'Dockerfile' 'docker-compose*.yml' 'phpunit.xml' '*.sql' '*.sqlite' '*.db' '.DS_Store' 'Thumbs.db' '*.zip' 'backup/*' 'database/*' 'tmp/*' 'uploads/*' 'temp/*'

echo "Archive created: exam-system.zip"
