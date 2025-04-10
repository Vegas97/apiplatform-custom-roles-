#!/usr/bin/env bash

# Define project name for network and container naming
PROJECT_NAME="apiplatform-custom-roles"

# # Create Docker network if it doesn't exist
# if [[ "$(docker network ls | grep "${PROJECT_NAME}_network")" == "" ]] ; then
#     echo "Creating Docker network: ${PROJECT_NAME}_network"
#     docker network create --driver=bridge --ipam-driver=default --subnet=172.18.2.0/24 --gateway=172.18.2.1 ${PROJECT_NAME}_network
# fi

# Start Docker containers
echo "Starting Docker containers..."
docker compose up -d

# Connect to the PHP container
echo "Connecting to PHP container..."
docker exec -it -w /var/www/symfony ${PROJECT_NAME}--php-1 /bin/sh

# When exiting the container, show a message
echo "Exited from container. Containers are still running."
echo "To stop containers, run: docker compose down"