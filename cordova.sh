#!/bin/bash

echo "Downloading release script..."
wget https://dw.ng.nightit.org/cordova/release.sh

if [[ ! -f "release.sh" ]]
then
    echo "Error downloading release script"
    exit
else
     chmod +x release.sh
     rm cordova.sh
     mv release.sh cordova.sh
     ./cordova.sh $@
fi