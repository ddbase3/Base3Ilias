#!/bin/bash

cd ../../../../public || exit 1

while true
do
        php base3.php -f --name=masterworker
        sleep 1
done

