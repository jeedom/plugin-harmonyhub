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
sudo apt-get install -y python-pip python-dev
echo 60 > ${PROGRESS_FILE}
sudo pip install requests
echo 100 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
rm ${PROGRESS_FILE}
