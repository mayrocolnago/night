#!/bin/bash

bash -c "nohup setsid find /tmp -type f -atime +10 -delete" > /dev/null 2>&1 &

bash -c "echo . > /var/www/access.log"
bash -c "echo . > /var/www/error.log"