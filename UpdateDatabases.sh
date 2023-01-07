#!/bin/bash

damwidiCmd="php damwidiMain.php"

echo 'update damwidi databases start:' $(date +"%Y-%m-%d %T") > ./logs/updateDatabases.txt
eval "$damwidiCmd" updateBivioTransactions    >> ./logs/updateDatabases.txt && \
    eval "$damwidiCmd" updatePerformanceData  >> ./logs/updateDatabases.txt && \
    eval "$damwidiCmd" updateValueTable       >> ./logs/updateDatabases.txt && \
    eval "$damwidiCmd" updateHistoryTable     >> ./logs/updateDatabases.txt
echo >> ./logs/updateDatabases.txt
echo 'update damwidi databases end:  ' $(date +"%Y-%m-%d %T")  >> ./logs/updateDatabases.txt

# cd /home3/yurtkura/public_html/DAMWIDIsite && /usr/local/bin/php damwidiMain.php updateBivioTransactions > ./logs/transactions.txt