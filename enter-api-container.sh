#!/bin/bash

# Script to enter the API Platform PHP container
# This script will open a shell in the PHP container using docker compose

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the project directory
cd "$SCRIPT_DIR"

# Enter the PHP container using docker compose
docker compose exec php sh

echo "Exited from API container"
