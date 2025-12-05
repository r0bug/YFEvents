#!/bin/bash
# Pre-commit security check script

echo "Running security checks..."

# Check for Google API keys
if git diff --cached | grep -qE "AIza[0-9A-Za-z_-]{35}"; then
    echo "ERROR: Google API key detected in staged changes!"
    exit 1
fi

# Check for hardcoded passwords
if git diff --cached | grep -qE "password\s*=\s*['\"][^'\"]{8,}['\"]"; then
    echo "WARNING: Potential hardcoded password detected"
    # Don't exit - just warn
fi

# Check for AWS keys
if git diff --cached | grep -qE "AKIA[0-9A-Z]{16}"; then
    echo "ERROR: AWS access key detected!"
    exit 1
fi

echo "Security checks passed!"
exit 0
