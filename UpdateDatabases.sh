#!/bin/bash

damwidiCmd="/usr/local/bin/php damwidiMain.php"
#damwidiCmd="php damwidiMain.php"

cd /home3/yurtkura/public_html/DAMWIDIsite
echo 'update damwidi databases start:' $(date +"%Y-%m-%d %T") > ./logs/updateDatabases.txt
eval "$damwidiCmd" updateBivioTransactions    >> ./logs/updateDatabases.txt && \
    eval "$damwidiCmd" updatePerformanceData  >> ./logs/updateDatabases.txt && \
    eval "$damwidiCmd" updateValueTable       >> ./logs/updateDatabases.txt && \
    eval "$damwidiCmd" updateHistoryTable     >> ./logs/updateDatabases.txt
echo >> ./logs/updateDatabases.txt
echo 'update damwidi databases end:  ' $(date +"%Y-%m-%d %T")  >> ./logs/updateDatabases.txt

# cd /home3/yurtkura/public_html/DAMWIDIsite && /usr/local/bin/php damwidiMain.php updateBivioTransactions > ./logs/transactions.txt
# cd /home3/yurtkura/public_html/DAMWIDIsite && /usr/local/bin/php damwidiMain.php updateValueTable > ./logs/value.txt
# cd /home3/yurtkura/public_html/DAMWIDIsite && /usr/local/bin/php damwidiMain.php updatePerformanceData > ./logs/performance.txt
# cd /home3/yurtkura/public_html/DAMWIDIsite && /usr/local/bin/php damwidiMain.php updateHistoryTable > ./logs/history.txt