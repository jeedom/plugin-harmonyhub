#!/bin/bash
PROGRESS_FILE=/tmp/dependancy_harmonyhub_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation des dépendances             *"
echo "********************************************************"
sudo apt-get update  -y -q
echo 50 > ${PROGRESS_FILE}
sudo apt-get install -y python3-pip python3-dev python3-setuptools
echo 60 > ${PROGRESS_FILE}
sudo pip3 install requests
sudo pip3 install asyncio
sudo pip3 install websockets
sudo pip3 install aiohttp
echo 100 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
rm ${PROGRESS_FILE}
